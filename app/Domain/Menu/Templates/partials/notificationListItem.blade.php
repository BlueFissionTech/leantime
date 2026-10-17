<li
    @if (($notif['read'] ?? 1) == 0)
        class='new'
    @endif
    data-url="{{ $notif['url'] }}"
    data-id="{{ $notif['id'] }}"
>
    <a href="{{ $notif['url'] }}">
        <span class="notificationProfileImage">
            <img src="{{ BASE_URL }}/api/users?profileImage={{ $notif['authorId'] }}"/>
        </span>
        <span class="notificationDate">
            {{ format($notif['datetime'])->date() }}
            {{ format($notif['datetime'])->time() }}
        </span>
        <span class="notificationTitle">{!! strip_tags($tpl->convertRelativePaths($notif['message'])) !!}</span>
    </a>
</li>
