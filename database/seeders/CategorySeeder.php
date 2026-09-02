<?php

namespace Database\Seeders;

use App\Data\DecorationData;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** @var array<int, array{name: string, icon_slug: string, color_slug: string, fixed: bool, children: array<int, array{name: string, icon_slug: string, color_slug: string, fixed: bool}>}> */
    private array $structure = [
        // Income - Green
        [
            'name' => 'Income', 'order' => 1, 'icon_slug' => 'round-arrow-down', 'color_slug' => 'green-700', 'fixed' => false,
            'children' => [
                ['name' => 'Salary', 'icon_slug' => 'case', 'color_slug' => 'green-700', 'fixed' => true],
                ['name' => 'Freelance', 'icon_slug' => 'laptop', 'color_slug' => 'green-700', 'fixed' => false],
                ['name' => 'Business Revenue', 'icon_slug' => 'shop', 'color_slug' => 'green-700', 'fixed' => false],
                ['name' => 'Grants & Stipends', 'icon_slug' => 'hand-shake', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Investment Returns', 'icon_slug' => 'course-up', 'color_slug' => 'green-400', 'fixed' => false],
                ['name' => 'Dividends', 'icon_slug' => 'course-up', 'color_slug' => 'green-400', 'fixed' => false],
                ['name' => 'Other Income', 'icon_slug' => 'add-circle', 'color_slug' => 'green-200', 'fixed' => false],
            ],
        ],
        // Finance - Green
        [
            'name' => 'Finance',  'order' => 99, 'icon_slug' => 'hand-money', 'color_slug' => 'green-700', 'fixed' => true,
            'children' => [
                ['name' => 'Admin Fees', 'icon_slug' => 'wallet', 'color_slug' => 'green-700', 'fixed' => false],
                ['name' => 'Taxes', 'icon_slug' => 'wallet', 'color_slug' => 'green-700', 'fixed' => false],
                ['name' => 'Interest', 'icon_slug' => 'wallet', 'color_slug' => 'green-600', 'fixed' => false],
                ['name' => 'Insurance', 'icon_slug' => 'shield-check', 'color_slug' => 'green-500', 'fixed' => true],
            ],
        ],
        // Food & Drinks - red
        [
            'name' => 'Food & Drinks',  'order' => 1, 'icon_slug' => 'plate', 'color_slug' => 'red-700',
            'children' => [
                ['name' => 'Dining Out', 'icon_slug' => 'donut', 'color_slug' => 'red-700'],
                ['name' => 'Snacks & Drinks', 'icon_slug' => 'cup-hot', 'color_slug' => 'red-600'],
                ['name' => 'Coffee & Desserts ', 'icon_slug' => 'mug', 'color_slug' => 'red-600'],
                ['name' => 'Food Takeouts', 'icon_slug' => 'donut', 'color_slug' => 'red-500'],
                ['name' => 'Buffet / Fine Dining', 'icon_slug' => 'chef-hat', 'color_slug' => 'red-400'],
            ],
        ],
        // Utilities - amber
        [
            'name' => 'Utilities',  'order' => 2, 'icon_slug' => 'lightning', 'color_slug' => 'amber-600', 'fixed' => true,
            'children' => [
                ['name' => 'Electricity', 'icon_slug' => 'lightning', 'color_slug' => 'amber-600', 'fixed' => true],
                ['name' => 'Water', 'icon_slug' => 'waterdrop', 'color_slug' => 'amber-500', 'fixed' => true],
                ['name' => 'Gas & Cooking', 'icon_slug' => 'fire', 'color_slug' => 'amber-500', 'fixed' => false],
                ['name' => 'Internet', 'icon_slug' => 'wi-fi-high', 'color_slug' => 'amber-400', 'fixed' => true],
                ['name' => 'Mobile & Prepaid', 'icon_slug' => 'smartphone', 'color_slug' => 'amber-400', 'fixed' => false],
            ],
        ],
        // Service & Housing - yellow
        [
            'name' => 'Service & Housing',  'order' => 3, 'icon_slug' => 'house', 'color_slug' => 'yellow-600', 'fixed' => true,
            'children' => [
                ['name' => 'Rent', 'icon_slug' => 'key', 'color_slug' => 'yellow-600', 'fixed' => true],
                ['name' => 'Home Maintenance', 'icon_slug' => 'settings-minimalistic', 'color_slug' => 'yellow-500', 'fixed' => false],
                ['name' => 'Cleaning Services', 'icon_slug' => 'broom', 'color_slug' => 'yellow-400', 'fixed' => false],
                ['name' => 'Property Services', 'icon_slug' => 'buildings', 'color_slug' => 'yellow-400', 'fixed' => false],
            ],
        ],
        // Shopping - Sky
        [
            'name' => 'Shopping', 'order' => 4, 'icon_slug' => 'bag', 'color_slug' => 'sky-600', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon_slug' => 'cart-large', 'color_slug' => 'sky-600', 'fixed' => false],
                ['name' => 'Clothing', 'icon_slug' => 'bag-2', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Beauty & Grooming', 'icon_slug' => 'scissors', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Electronics', 'icon_slug' => 'smartphone', 'color_slug' => 'sky-400', 'fixed' => false],
                ['name' => 'Household Items', 'icon_slug' => 'lamp', 'color_slug' => 'sky-400', 'fixed' => false],
            ],
        ],
        // Entertainment & Leisure - cyan
        [
            'name' => 'Entertainment & Leisure', 'order' => 5, 'icon_slug' => 'gamepad', 'color_slug' => 'cyan-600',
            'children' => [
                ['name' => 'Hobbies', 'icon_slug' => 'paint-brush', 'color_slug' => 'cyan-600'],
                ['name' => 'Sports', 'icon_slug' => 'basketball', 'color_slug' => 'cyan-500'],
                ['name' => 'Games', 'icon_slug' => 'gamepad', 'color_slug' => 'cyan-500'],
                ['name' => 'Cinema & Shows', 'icon_slug' => 'tv', 'color_slug' => 'cyan-400'],
                ['name' => 'Streaming', 'icon_slug' => 'tv', 'color_slug' => 'cyan-400'],
                ['name' => 'Travel & Tourism', 'icon_slug' => 'plane', 'color_slug' => 'cyan-300'],
                ['name' => 'Festivals / Events', 'icon_slug' => 'confetti', 'color_slug' => 'cyan-300'],
            ],
        ],
        // Transport - Slate
        [
            'name' => 'Transport', 'order' => 6, 'icon_slug' => 'wheel-angle', 'color_slug' => 'slate-900', 'fixed' => false,
            'children' => [
                ['name' => 'Fuel', 'icon_slug' => 'fuel', 'color_slug' => 'slate-900', 'fixed' => true],
                ['name' => 'Public Transport', 'icon_slug' => 'bus', 'color_slug' => 'slate-800', 'fixed' => true],
                ['name' => 'Ride-hailing / Taxis', 'icon_slug' => 'scooter', 'color_slug' => 'slate-800', 'fixed' => false],
                ['name' => 'Bus / Trains', 'icon_slug' => 'tram', 'color_slug' => 'slate-700', 'fixed' => false],
                ['name' => 'Flights / Ferries', 'icon_slug' => 'plane', 'color_slug' => 'slate-700', 'fixed' => false],
                ['name' => 'Travel Services', 'icon_slug' => 'suitcase', 'color_slug' => 'slate-600', 'fixed' => false],
            ],
        ],
        // Health & Wellness - Rose
        [
            'name' => 'Health & Wellness', 'order' => 7, 'icon_slug' => 'heart-pulse', 'color_slug' => 'rose-700', 'fixed' => false,
            'children' => [
                ['name' => 'Medicine', 'icon_slug' => 'pill', 'color_slug' => 'rose-400', 'fixed' => false],
                ['name' => 'Doctor Visits', 'icon_slug' => 'stethoscope', 'color_slug' => 'rose-300', 'fixed' => false],
                ['name' => 'Traditional Therapy', 'icon_slug' => 'leaf', 'color_slug' => 'rose-500', 'fixed' => false],
                ['name' => 'Fitness & Gyms', 'icon_slug' => 'dumbbell-large', 'color_slug' => 'rose-600', 'fixed' => true],
                ['name' => 'Family Care', 'icon_slug' => 'users-group-two-rounded', 'color_slug' => 'rose-700', 'fixed' => false],
            ],
        ],
        // Education - lime
        [
            'name' => 'Education', 'order' => 8, 'icon_slug' => 'square-academic-cap', 'color_slug' => 'lime-700',
            'children' => [
                ['name' => 'Tuition', 'icon_slug' => 'book', 'color_slug' => 'lime-700', 'fixed' => true],
                ['name' => 'University / School Fees', 'icon_slug' => 'diploma', 'color_slug' => 'lime-600', 'fixed' => true],
                ['name' => 'Books & Stationery', 'icon_slug' => 'library', 'color_slug' => 'lime-500'],
                ['name' => 'Courses & Workshops', 'icon_slug' => 'server-square', 'color_slug' => 'lime-400'],
            ],
        ],
        // Socials - Violet
        [
            'name' => 'Socials', 'order' => 9, 'icon_slug' => 'heart', 'color_slug' => 'violet-700', 'fixed' => false,
            'children' => [
                ['name' => 'Family & Friends', 'icon_slug' => 'users-group-two-rounded', 'color_slug' => 'violet-700', 'fixed' => false],
                ['name' => 'Gifts', 'icon_slug' => 'gift', 'color_slug' => 'violet-600', 'fixed' => false],
                ['name' => 'Charity & Donations', 'icon_slug' => 'hand-heart', 'color_slug' => 'violet-500', 'fixed' => false],
            ],
        ],
    ];

    public function run(): void
    {
        $this->seedSystemCategories();
    }

    public function seedSystemCategories(): void
    {
        foreach ($this->structure as $index => $group) {
            $topOrder = sprintf('0.%03d', 100 + ($index * 90));
            $parent = Category::create([
                'parent_id' => null,
                'name' => $group['name'],
                'type' => $group['name'] === 'Income' ? 'input' : 'output',
                'order' => $group['order'] ?? $topOrder,
                'decorations' => $this->resolveDecoration($group['icon_slug'], $group['color_slug']),
                'is_fixed_cost' => $group['fixed'] ?? false,
            ]);

            foreach ($group['children'] as $childIndex => $child) {
                $childOrder = sprintf('0.%03d', 100 + ($index * 90) + $childIndex + 1);
                Category::create([
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'type' => $group['name'] === 'Income' ? 'input' : 'output',
                    'order' => $childOrder,
                    'decorations' => $this->resolveDecoration($child['icon_slug'], $child['color_slug']),
                    'is_fixed_cost' => $child['fixed'] ?? false,
                ]);
            }
        }
    }

    private function resolveDecoration(string $iconSlug, string $colorSlug): array
    {
        return DecorationData::from([
            'icon' => $iconSlug,
            'color' => $colorSlug,
        ])->toArray();
    }
}
