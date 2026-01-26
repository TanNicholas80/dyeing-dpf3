<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel untuk dashboard proses statuses - hanya untuk user yang terautentikasi
Broadcast::channel('dashboard.proses-statuses', function ($user) {
    // Hanya user yang terautentikasi dan memiliki role yang diizinkan
    $allowedRoles = ['super_admin', 'ds', 'mesin', 'ppic', 'fm', 'vp', 'owner'];
    return $user && in_array($user->role, $allowedRoles);
});
