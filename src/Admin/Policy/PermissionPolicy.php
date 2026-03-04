<?php

namespace CargoDocsStudio\Admin\Policy;

class PermissionPolicy
{
    public function can(string $capability): bool
    {
        return current_user_can($capability);
    }
}
