#!/usr/bin/env python3
import argparse
import pathlib
import re
import sqlite3
import sys


SKIP_PREFIXES = (
    "SET ",
    "START TRANSACTION",
    "COMMIT",
    "LOCK TABLES",
    "UNLOCK TABLES",
)


def iter_mysql_statements(path: pathlib.Path):
    buffer = []
    in_string = False
    escape = False
    quote = ""

    with path.open("r", encoding="utf-8", errors="replace") as handle:
        for raw_line in handle:
            stripped = raw_line.lstrip()

            if not in_string:
                if stripped.startswith("--"):
                    continue
                if stripped.startswith("/*!") or stripped.startswith("/*"):
                    continue

            for char in raw_line:
                buffer.append(char)

                if in_string:
                    if escape:
                        escape = False
                        continue
                    if char == "\\":
                        escape = True
                        continue
                    if char == quote:
                        in_string = False
                        quote = ""
                    continue

                if char in ("'", '"'):
                    in_string = True
                    quote = char
                    continue

                if char == ";":
                    statement = "".join(buffer).strip()
                    buffer = []
                    if statement:
                        yield statement

    trailing = "".join(buffer).strip()
    if trailing:
        yield trailing


def normalize_string_literals(statement: str) -> str:
    out = []
    in_string = False
    escape = False
    quote = ""
    literal = []

    def flush_literal():
        nonlocal literal
        text = "".join(literal)
        text = (
            text.replace("\\0", "\x00")
            .replace("\\b", "\b")
            .replace("\\n", "\n")
            .replace("\\r", "\r")
            .replace("\\t", "\t")
            .replace("\\Z", "\x1a")
            .replace("\\'", "'")
            .replace('\\"', '"')
            .replace("\\\\", "\\")
        )
        out.append("'" + text.replace("'", "''") + "'")
        literal = []

    for char in statement:
        if in_string:
            if escape:
                literal.append("\\" + char)
                escape = False
                continue
            if char == "\\":
                escape = True
                continue
            if char == quote:
                flush_literal()
                in_string = False
                quote = ""
                continue
            literal.append(char)
            continue

        if char in ("'", '"'):
            in_string = True
            quote = char
            literal = []
            continue

        out.append(char)

    if in_string:
        raise ValueError("unterminated SQL string literal")

    return "".join(out)


def convert_create_table(statement: str) -> str:
    stmt = normalize_string_literals(statement)
    stmt = re.sub(r"\)\s*ENGINE=.*?$", ")", stmt, flags=re.IGNORECASE | re.DOTALL)
    stmt = re.sub(r"AUTO_INCREMENT=\d+\s*", "", stmt, flags=re.IGNORECASE)

    head_start = stmt.find("(")
    tail_end = stmt.rfind(")")
    if head_start == -1 or tail_end == -1 or tail_end <= head_start:
        return stmt

    head = stmt[: head_start + 1]
    body = stmt[head_start + 1 : tail_end]
    rows = []

    for raw_line in body.splitlines():
        line = raw_line.strip().rstrip(",")
        if not line:
            continue
        upper = line.upper()
        if upper.startswith("KEY ") or upper.startswith("UNIQUE KEY ") or upper.startswith("CONSTRAINT "):
            continue

        line = re.sub(r"\bUNSIGNED\b", "", line, flags=re.IGNORECASE)
        line = re.sub(r"\bCHARACTER SET\s+\w+\b", "", line, flags=re.IGNORECASE)
        line = re.sub(r"\bCOLLATE\s+\w+\b", "", line, flags=re.IGNORECASE)
        line = re.sub(r"\bAUTO_INCREMENT\b", "", line, flags=re.IGNORECASE)
        line = re.sub(r"\bON UPDATE CURRENT_TIMESTAMP(?:\(\))?\b", "", line, flags=re.IGNORECASE)
        line = re.sub(r"\benum\s*\([^)]*\)", "TEXT", line, flags=re.IGNORECASE)
        line = re.sub(r"\bset\s*\([^)]*\)", "TEXT", line, flags=re.IGNORECASE)
        line = re.sub(r"\s{2,}", " ", line).strip()
        rows.append(line)

    return head + "\n  " + ",\n  ".join(rows) + "\n)"


def should_skip(statement: str) -> bool:
    upper = statement.upper()
    return upper.startswith(SKIP_PREFIXES)


def convert_statement(statement: str) -> str | None:
    stmt = statement.strip().rstrip(";")
    if not stmt or should_skip(stmt):
        return None

    upper = stmt.upper()
    if upper.startswith("CREATE TABLE"):
        return convert_create_table(stmt)
    if upper.startswith("REPLACE INTO"):
        return normalize_string_literals(re.sub(r"^REPLACE INTO", "INSERT OR REPLACE INTO", stmt, flags=re.IGNORECASE))
    if upper.startswith("INSERT INTO"):
        return normalize_string_literals(stmt)
    if upper.startswith("DROP TABLE"):
        return stmt
    if upper.startswith("ALTER TABLE"):
        return None

    return normalize_string_literals(stmt)


def import_dump(dump_path: pathlib.Path, sqlite_path: pathlib.Path):
    sqlite_path.parent.mkdir(parents=True, exist_ok=True)
    if sqlite_path.exists():
        sqlite_path.unlink()

    conn = sqlite3.connect(sqlite_path)
    conn.execute("PRAGMA foreign_keys = OFF")
    conn.execute("PRAGMA journal_mode = WAL")
    conn.execute("PRAGMA synchronous = NORMAL")

    applied = 0
    skipped = 0

    try:
        for statement in iter_mysql_statements(dump_path):
            converted = convert_statement(statement)
            if not converted:
                skipped += 1
                continue
            conn.executescript(converted + ";\n")
            applied += 1
            if applied % 100 == 0:
                conn.commit()
        conn.commit()
    finally:
        conn.close()

    return applied, skipped


def main():
    parser = argparse.ArgumentParser(description="Convert a MySQL/MariaDB dump into a local SQLite database.")
    parser.add_argument("dump", type=pathlib.Path, help="Path to the MySQL dump file")
    parser.add_argument("sqlite_db", type=pathlib.Path, help="Path to the target SQLite database file")
    args = parser.parse_args()

    try:
        applied, skipped = import_dump(args.dump, args.sqlite_db)
    except Exception as exc:
        print(f"Import failed: {exc}", file=sys.stderr)
        return 1

    print(f"Imported SQLite DB: {args.sqlite_db}")
    print(f"Applied statements: {applied}")
    print(f"Skipped statements: {skipped}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
