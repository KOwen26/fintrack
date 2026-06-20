<?php

namespace Database\Seeders;

use App\Data\DecorationData;
use App\Models\Category;
use App\Models\DecorationColor;
use App\Models\DecorationIcon;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** @var array<int, array{name: string, icon_slug: string, color_slug: string, fixed: bool, children: array<int, array{name: string, icon_slug: string, color_slug: string, fixed: bool}>}> */
    private array $structure = [
        [
            'name' => 'Income', 'icon_slug' => 'arrow-circle-down-bold', 'color_slug' => 'green-500', 'fixed' => false,
            'children' => [
                ['name' => 'Salary', 'icon_slug' => 'briefcase-bold', 'color_slug' => 'green-500', 'fixed' => true],
                ['name' => 'Freelance', 'icon_slug' => 'laptop-bold', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Business Revenue', 'icon_slug' => 'storefront-bold', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Grants & Stipends', 'icon_slug' => 'handshake-bold', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Investment Returns', 'icon_slug' => 'trend-up-bold', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Dividends', 'icon_slug' => 'trend-up-bold', 'color_slug' => 'green-500', 'fixed' => false],
                ['name' => 'Other Income', 'icon_slug' => 'plus-circle-bold', 'color_slug' => 'green-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Food & Drinks', 'icon_slug' => 'fork-knife-bold', 'color_slug' => 'orange-500', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon_slug' => 'shopping-cart-bold', 'color_slug' => 'orange-500', 'fixed' => false],
                ['name' => 'Dining Out', 'icon_slug' => 'hamburger-bold', 'color_slug' => 'orange-500', 'fixed' => false],
                ['name' => 'Coffee & Snacks', 'icon_slug' => 'coffee-bold', 'color_slug' => 'orange-500', 'fixed' => false],
                ['name' => 'Desserts & Drinks', 'icon_slug' => 'cup-bold', 'color_slug' => 'orange-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Transport', 'icon_slug' => 'car-bold', 'color_slug' => 'blue-500', 'fixed' => false,
            'children' => [
                ['name' => 'Fuel', 'icon_slug' => 'gas-pump-bold', 'color_slug' => 'blue-500', 'fixed' => true],
                ['name' => 'Public Transport', 'icon_slug' => 'bus-bold', 'color_slug' => 'blue-500', 'fixed' => true],
                ['name' => 'Ride-hailing / Taxis', 'icon_slug' => 'motorcycle-bold', 'color_slug' => 'blue-500', 'fixed' => false],
                ['name' => 'Travel Services', 'icon_slug' => 'train-bold', 'color_slug' => 'blue-500', 'fixed' => false],
                ['name' => 'Bus / Trains', 'icon_slug' => 'train-bold', 'color_slug' => 'blue-500', 'fixed' => false],
                ['name' => 'Flights / Ferries', 'icon_slug' => 'train-bold', 'color_slug' => 'blue-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Housing', 'icon_slug' => 'house-bold', 'color_slug' => 'violet-500', 'fixed' => true,
            'children' => [
                ['name' => 'Rent', 'icon_slug' => 'key-bold', 'color_slug' => 'violet-500', 'fixed' => true],
                ['name' => 'Home Maintenance', 'icon_slug' => 'wrench-bold', 'color_slug' => 'violet-500', 'fixed' => false],
                ['name' => 'Cleaning Services', 'icon_slug' => 'broom-bold', 'color_slug' => 'violet-500', 'fixed' => false],
                ['name' => 'Property Services', 'icon_slug' => 'building-bold', 'color_slug' => 'violet-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Utilities', 'icon_slug' => 'lightning-bold', 'color_slug' => 'yellow-500', 'fixed' => true,
            'children' => [
                ['name' => 'Electricity', 'icon_slug' => 'lightning-bold', 'color_slug' => 'yellow-500', 'fixed' => true],
                ['name' => 'Water', 'icon_slug' => 'drop-bold', 'color_slug' => 'yellow-500', 'fixed' => true],
                ['name' => 'Internet', 'icon_slug' => 'wifi-bold', 'color_slug' => 'yellow-500', 'fixed' => true],
                ['name' => 'Mobile & Prepaid', 'icon_slug' => 'smartphone-bold', 'color_slug' => 'yellow-500', 'fixed' => false],
                ['name' => 'Gas & Cooking', 'icon_slug' => 'fire-bold', 'color_slug' => 'yellow-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Health & Wellness', 'icon_slug' => 'heart-beat-bold', 'color_slug' => 'red-500', 'fixed' => false,
            'children' => [
                ['name' => 'Doctor Visits', 'icon_slug' => 'stethoscope-bold', 'color_slug' => 'red-500', 'fixed' => false],
                ['name' => 'Medicine', 'icon_slug' => 'pill-bold', 'color_slug' => 'red-500', 'fixed' => false],
                ['name' => 'Traditional Therapy', 'icon_slug' => 'leaf-bold', 'color_slug' => 'red-500', 'fixed' => false],
                ['name' => 'Fitness & Gyms', 'icon_slug' => 'barbell-bold', 'color_slug' => 'red-500', 'fixed' => true],
                ['name' => 'Family Care', 'icon_slug' => 'people-bold', 'color_slug' => 'red-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Shopping', 'icon_slug' => 'bag-bold', 'color_slug' => 'teal-500', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon_slug' => 'shopping-cart-bold', 'color_slug' => 'teal-500', 'fixed' => false],
                ['name' => 'Clothing', 'icon_slug' => 'shopping-bags-bold', 'color_slug' => 'teal-500', 'fixed' => false],
                ['name' => 'Beauty & Grooming', 'icon_slug' => 'scissors-bold', 'color_slug' => 'teal-500', 'fixed' => false],
                ['name' => 'Electronics', 'icon_slug' => 'device-mobile-bold', 'color_slug' => 'teal-500', 'fixed' => false],
                ['name' => 'Household Items', 'icon_slug' => 'lamp-bold', 'color_slug' => 'teal-500', 'fixed' => false],
                ['name' => 'Gifts & Festivals', 'icon_slug' => 'gifts-bold', 'color_slug' => 'teal-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Education', 'icon_slug' => 'graduation-cap-bold', 'color_slug' => 'indigo-500', 'fixed' => false,
            'children' => [
                ['name' => 'Tuition', 'icon_slug' => 'book-open-bold', 'color_slug' => 'indigo-500', 'fixed' => false],
                ['name' => 'University / School Fees', 'icon_slug' => 'student-bold', 'color_slug' => 'indigo-500', 'fixed' => true],
                ['name' => 'Books & Stationery', 'icon_slug' => 'books-bold', 'color_slug' => 'indigo-500', 'fixed' => false],
                ['name' => 'Courses', 'icon_slug' => 'computer-tower-bold', 'color_slug' => 'indigo-500', 'fixed' => false],
                ['name' => 'Workshops', 'icon_slug' => 'lightbulb-bold', 'color_slug' => 'indigo-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Entertainment & Leisure', 'icon_slug' => 'game-controller-bold', 'color_slug' => 'pink-500', 'fixed' => false,
            'children' => [
                ['name' => 'Games', 'icon_slug' => 'game-controller-bold', 'color_slug' => 'pink-500', 'fixed' => false],
                ['name' => 'Streaming', 'icon_slug' => 'television-bold', 'color_slug' => 'pink-500', 'fixed' => true],
                ['name' => 'Cinema & Shows', 'icon_slug' => 'television-bold', 'color_slug' => 'pink-500', 'fixed' => false],
                ['name' => 'Hobbies', 'icon_slug' => 'paint-brush-bold', 'color_slug' => 'pink-500', 'fixed' => false],
                ['name' => 'Travel & Tourism', 'icon_slug' => 'airplane-bold', 'color_slug' => 'pink-500', 'fixed' => false],
                ['name' => 'Festivals / Events', 'icon_slug' => 'party-popper-bold', 'color_slug' => 'pink-500', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Finance', 'icon_slug' => 'coins-bold', 'color_slug' => 'sky-500', 'fixed' => true,
            'children' => [
                ['name' => 'Admin Fees', 'icon_slug' => 'wallet-bold', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Taxes', 'icon_slug' => 'wallet-bold', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Interest', 'icon_slug' => 'wallet-bold', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Savings', 'icon_slug' => 'wallet-bold', 'color_slug' => 'sky-500', 'fixed' => true],
                ['name' => 'Loans & Repayments', 'icon_slug' => 'receipt-bold', 'color_slug' => 'sky-500', 'fixed' => true],
                ['name' => 'Insurance', 'icon_slug' => 'shield-check-bold', 'color_slug' => 'sky-500', 'fixed' => true],
                ['name' => 'Investments', 'icon_slug' => 'chart-line-bold', 'color_slug' => 'sky-500', 'fixed' => false],
                ['name' => 'Charity & Donations', 'icon_slug' => 'heart-plus-bold', 'color_slug' => 'sky-500', 'fixed' => false],
            ],
        ],
    ];

    private function resolveDecoration(string $iconSlug, string $colorSlug): array
    {
        $icon = DecorationIcon::where('slug', $iconSlug)->firstOrFail();
        $color = DecorationColor::where('slug', $colorSlug)->firstOrFail();

        return DecorationData::from([
            'icon' => ['id' => $icon->slug, 'value' => $icon->value],
            'color' => ['id' => $color->slug, 'value' => $color->hex],
        ])->toArray();
    }

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
                'order' => $topOrder,
                'decorations' => $this->resolveDecoration($group['icon_slug'], $group['color_slug']),
                'is_fixed_cost' => $group['fixed'],
            ]);

            foreach ($group['children'] as $childIndex => $child) {
                $childOrder = sprintf('0.%03d', 100 + ($index * 90) + $childIndex + 1);
                Category::create([
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'type' => $group['name'] === 'Income' ? 'input' : 'output',
                    'order' => $childOrder,
                    'decorations' => $this->resolveDecoration($child['icon_slug'], $child['color_slug']),
                    'is_fixed_cost' => $child['fixed'],
                ]);
            }
        }
    }
}
