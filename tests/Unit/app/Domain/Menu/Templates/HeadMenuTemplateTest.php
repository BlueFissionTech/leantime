<?php

namespace Unit\app\Domain\Menu\Templates;

use Unit\TestCase;

class HeadMenuTemplateTest extends TestCase
{
    public function test_head_menu_template_exposes_activity_comments_and_mentions_tabs(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Menu/Templates/headMenu.blade.php');

        $this->assertIsString($template);
        $this->assertStringContainsString("toggleNotificationTabs('activity')", $template);
        $this->assertStringContainsString("toggleNotificationTabs('comments')", $template);
        $this->assertStringContainsString("toggleNotificationTabs('mentions')", $template);
        $this->assertStringContainsString("Activity ({{ \$totalNewActivity }})", $template);
        $this->assertStringContainsString("Comments ({{ \$totalNewComments }})", $template);
    }
}
