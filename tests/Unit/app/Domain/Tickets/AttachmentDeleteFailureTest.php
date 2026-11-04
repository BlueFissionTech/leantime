<?php

namespace Unit\app\Domain\Tickets;

use Illuminate\Container\Container;
use Illuminate\Session\Store;
use Leantime\Core\Language;
use Leantime\Core\UI\Template;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Comments\Services\Comments;
use Leantime\Domain\Files\Services\Files;
use Leantime\Domain\Projects\Services\Projects;
use Leantime\Domain\Sprints\Services\Sprints;
use Leantime\Domain\Supportcenter\Services\GithubElevation;
use Leantime\Domain\Tickets\Controllers\ShowTicket;
use Leantime\Domain\Tickets\Models\Tickets as Ticket;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Timesheets\Services\Timesheets;
use Leantime\Domain\Users\Services\Users;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class AttachmentDeleteFailureTest extends TestCase
{
    public static function rejectedDeletes(): array
    {
        return [
            'storage rejects matching file' => ['ticket', 42, Roles::$editor, true],
            'another ticket' => ['ticket', 43, Roles::$editor, false],
            'same number in another module' => ['project', 42, Roles::$editor, false],
            'read-only actor' => ['ticket', 42, Roles::$readonly, false],
        ];
    }

    /** @dataProvider rejectedDeletes */
    public function testFailedDeleteReportsErrorAndRendersTicketAgain(string $module, int $moduleId, string $role, bool $attempt): void
    {
        $ticket = new Ticket;
        $ticket->id = 42;
        $ticket->projectId = 7;
        $tickets = $this->createStub(Tickets::class);
        $tickets->method('getTicket')->willReturn($ticket);
        $files = $this->createMock(Files::class);
        $files->expects($role === Roles::$editor ? self::once() : self::never())->method('getFile')->with(19)->willReturn(['module' => $module, 'moduleId' => $moduleId]);
        $files->expects($attempt ? self::once() : self::never())->method('deleteFile')->with(19)->willReturn(false);
        $files->method('getFilesByModule')->willReturn([]);
        $comments = $this->createStub(Comments::class);
        $comments->method('getComments')->willReturn([]);

        $language = $this->createMock(Language::class);
        $language->expects(self::once())->method('__')->with('notifications.file_deleted_error')->willReturn('File could not be deleted');
        $view = $this->createMock(Template::class);
        $view->expects(self::once())->method('setNotification')->with('File could not be deleted', 'error');
        $response = new Response;
        $view->expects(self::once())->method('displayPartial')->with('tickets.showTicketModal')->willReturn($response);

        // Exercise the controller without booting the application or touching storage.
        $controller = new class ($view, $language) extends ShowTicket {
            public function __construct(Template $view, Language $language)
            {
                $this->tpl = $view;
                $this->language = $language;
            }
        };
        $session = $this->createStub(Store::class);
        $session->method('exists')->willReturn(true);
        $session->method('get')->willReturnCallback(static fn ($key) => [
            'currentProject' => 7,
            'userdata.id' => 1,
            'userdata.role' => $role,
            'usersettings.timezone' => 'UTC',
        ][$key] ?? null);
        $previous = Container::getInstance();
        $container = new Container;
        Container::setInstance($container);
        try {
            $container->instance('session', $session);
            $controller->init(
                $this->createStub(Projects::class),
                $tickets,
                $this->createStub(Sprints::class),
                $files,
                $comments,
                $this->createStub(Timesheets::class),
                $this->createStub(Users::class),
                $this->createStub(GithubElevation::class),
            );
            self::assertSame($response, $controller->get(['id' => 42, 'delFile' => 19]));
        } finally {
            Container::setInstance($previous);
        }
    }
}
