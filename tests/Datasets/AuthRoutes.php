<?php

dataset('authroutes', function () {
    return [
        '/',
        '/dashboard',
        '/profile/admin',
        '/notifications',
        '/settings/profile',
        '/settings/security',
        '/settings/sessions',
        '/settings/organization',
        '/settings/api',
        '/settings/subscription',
        '/settings/subscription/change-plan',
        '/settings/invoices',

        '/admin',
        '/admin/members-manager/members',
        '/admin/users',
        '/admin/members-manager/roles',
        '/admin/plans',
        '/admin/plans/1/edit',
        '/admin/changelogs',
        '/admin/changelogs/3/edit',
    ];
});
