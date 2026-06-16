<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Throwable;

class GeneratePermissionTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-permission-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate typescript file that contains permission types to be used by inertia frontend menu';

    private static function generateWildcardPermissions(array $permissions): array
    {
        // Use Collection to collect and deduplicate wildcards
        $wildcards = collect();

        foreach ($permissions as $permission) {
            // Add the original permission
            // $wildcards->push($permission);

            // Split permission into segments
            $segments = explode('.', (string) $permission);

            // Generate wildcard variants for permissions with multiple segments
            if (count($segments) > 1) {
                for ($i = 1; $i < count($segments); $i++) {
                    $wildcard = array_slice($segments, 0, $i);
                    $wildcard[] = '*';
                    $wildcards->push(implode('.', $wildcard));
                }
            }
        }

        // Return unique wildcard patterns as array
        return $wildcards->unique()->values()->toArray();
    }

    public function handle()
    {
        $permissions = Permission::orderBy('name')->pluck('name')->toArray();

        $fileName = 'permission.ts';
        $filePath = base_path('resources/js/types/' . $fileName);

        try {
            // Delete existing file
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            // Prepare TypeScript content
            $content = "// Auto-generated TypeScript permission types. CONSUME ONLY, DO NOT EDIT! \n";

            $content .= "export const permissions = [\n";
            foreach ($permissions as $index => $permission) {
                $content .= "    '{$permission}'";
                $content .= $index < count($permissions) - 1 ? ",\n" : "\n";
            }

            $content .= "] as const;\n\n";

            $wildcardPermissions = self::generateWildcardPermissions($permissions);
            $content .= "export const wildcardPermissions = [\n";
            foreach ($wildcardPermissions as $index => $permission) {
                $content .= "    '{$permission}'";
                $content .= $index < count($permissions) - 1 ? ",\n" : "\n";
            }

            $content .= "] as const;\n\n";

            $content .= "export type BasePermissions = typeof permissions[number];\n\n";
            $content .= "export type WildcardPermissions = typeof wildcardPermissions[number];\n\n";
            $content .= "export type Permissions = BasePermissions | WildcardPermissions;\n\n";

            // Ensure the directory exists
            $directory = dirname($filePath);
            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Write to file
            File::put($filePath, $content);

            $this->info("TypeScript permission types generated successfully at: {$filePath}");

        } catch (Throwable $throwable) {
            $this->error("Failed to generate permission types: {$throwable->getMessage()}");

            return 1; // Return non-zero for error
        }

        return 0; // Success
    }
}
