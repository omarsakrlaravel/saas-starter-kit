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
        '/settings/api',
        '/settings/subscription',
        '/settings/invoices',

        '/admin',
        '/admin/users',
        '/admin/users/1/edit',
        '/admin/roles',
        '/admin/roles/1/edit',
        '/admin/permissions',
        '/admin/permissions/create',
        '/admin/plans',
        '/admin/plans/1/edit',
        '/admin/changelogs',
        '/admin/changelogs/3/edit',
    ];
});
