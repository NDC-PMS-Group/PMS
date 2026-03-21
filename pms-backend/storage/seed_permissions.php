$permissions = [
    'dashboard.view', 'projects.view', 'projects.create', 'projects.update',
    'tasks.view', 'tasks.create', 'tasks.update', 'profile.view',
    'employee_profile.view', 'project_map.view'
];
$adminPermissions = [
    'access_settings.view', 'system_settings.view', 'activity_logs.view', 'organization.view'
];

$allPerms = array_merge($permissions, $adminPermissions);

foreach($allPerms as $p) {
    App\Models\Permission::firstOrCreate([
        'name' => $p
    ], [
        'action' => str_contains($p, 'create') ? 'create' : (str_contains($p, 'update') ? 'update' : 'view'),
        'resource' => explode('.', $p)[0]
    ]);
}

$roles = [2, 5, 6, 7, 8];
$pIds = App\Models\Permission::whereIn('name', $allPerms)->pluck('id');

foreach ($roles as $roleId) {
    $role = App\Models\Role::find($roleId);
    if ($role) {
        $role->permissions()->syncWithoutDetaching($pIds);
        echo "Updated permissions for role: {$role->name}\n";
    }
}
echo "Done.\n";
