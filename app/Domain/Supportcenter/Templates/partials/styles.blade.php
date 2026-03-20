@once
<style>
    .supportcenter-shell { display:grid; gap:20px; }
    .supportcenter-toolbar { display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:18px; }
    .supportcenter-list { display:grid; gap:14px; }
    .supportcenter-card, .supportcenter-panel { border:1px solid #dbe3ec; border-radius:16px; padding:18px; background:#fff; box-shadow:0 10px 30px rgba(15,23,42,.05); }
    .supportcenter-card { color:inherit; text-decoration:none; display:block; }
    .supportcenter-card.archived { opacity:.78; }
    .supportcenter-meta { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:12px; color:#667085; }
    .supportcenter-columns { display:grid; gap:20px; grid-template-columns:repeat(auto-fit, minmax(320px,1fr)); }
    .supportcenter-stack { display:grid; gap:18px; }
    .supportcenter-richtext { line-height:1.65; color:#1c2938; }
    .supportcenter-richtext img, .supportcenter-richtext video, .supportcenter-richtext iframe { max-width:100%; height:auto; border-radius:14px; }
    .supportcenter-richtext p:first-child { margin-top:0; }
    .supportcenter-richtext p:last-child { margin-bottom:0; }
    .supportcenter-comment { padding:14px 0; border-top:1px solid #e4e7ec; }
    .supportcenter-comment:first-child { border-top:0; padding-top:0; }
    .supportcenter-editor .tiptap-wrapper, .supportcenter-editor .tiptap-editor, .supportcenter-editor .tiptap-toolbar { width:100%; }
    .supportcenter-editor .tiptap-editor { border-radius:14px; background:#fff; }
    .supportcenter-editor .tiptap-toolbar { margin-bottom:8px; }
    .supportcenter-field-label { display:block; font-weight:600; margin-bottom:6px; }
</style>
@endonce
