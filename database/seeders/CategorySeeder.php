<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** @var array<int, array{name: string, icon: string, color: string, fixed: bool, children: array<int, array{name: string, icon: string, color: string, fixed: bool}>}> */
    private array $structure = [
        [
            'name' => 'Income', 'icon' => 'ph:arrow-circle-down-bold', 'color' => '#22c55e', 'fixed' => false,
            'children' => [
                ['name' => 'Salary', 'icon' => 'ph:briefcase-bold', 'color' => '#22c55e', 'fixed' => true],
                ['name' => 'Freelance', 'icon' => 'ph:laptop-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Business Revenue', 'icon' => 'ph:storefront-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Grants & Stipends', 'icon' => 'ph:handshake-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Investment Returns', 'icon' => 'ph:trend-up-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Dividends', 'icon' => 'ph:trend-up-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Other Income', 'icon' => 'ph:plus-circle-bold', 'color' => '#22c55e', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Food & Drinks', 'icon' => 'ph:fork-knife-bold', 'color' => '#f97316', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon' => 'ph:shopping-cart-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Dining Out', 'icon' => 'ph:hamburger-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Coffee & Snacks', 'icon' => 'ph:coffee-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Desserts & Drinks', 'icon' => 'ph:cup-bold', 'color' => '#f97316', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Transport', 'icon' => 'ph:car-bold', 'color' => '#3b82f6', 'fixed' => false,
            'children' => [
                ['name' => 'Fuel', 'icon' => 'ph:gas-pump-bold', 'color' => '#3b82f6', 'fixed' => true],
                ['name' => 'Public Transport', 'icon' => 'ph:bus-bold', 'color' => '#3b82f6', 'fixed' => true],
                ['name' => 'Ride-hailing / Taxis', 'icon' => 'ph:motorcycle-bold', 'color' => '#3b82f6', 'fixed' => false],
                ['name' => 'Travel Services', 'icon' => 'ph:train-bold', 'color' => '#3b82f6', 'fixed' => false],
                ['name' => 'Bus / Trains', 'icon' => 'ph:train-bold', 'color' => '#3b82f6', 'fixed' => false],
                ['name' => 'Flights / Ferries', 'icon' => 'ph:train-bold', 'color' => '#3b82f6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Housing', 'icon' => 'ph:house-bold', 'color' => '#8b5cf6', 'fixed' => true,
            'children' => [
                ['name' => 'Rent', 'icon' => 'ph:key-bold', 'color' => '#8b5cf6', 'fixed' => true],
                ['name' => 'Home Maintenance', 'icon' => 'ph:wrench-bold', 'color' => '#8b5cf6', 'fixed' => false],
                ['name' => 'Cleaning Services', 'icon' => 'ph:broom-bold', 'color' => '#8b5cf6', 'fixed' => false],
                ['name' => 'Property Services', 'icon' => 'ph:building-bold', 'color' => '#8b5cf6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Utilities', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true,
            'children' => [
                ['name' => 'Electricity', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Water', 'icon' => 'ph:drop-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Internet', 'icon' => 'ph:wifi-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Mobile & Prepaid', 'icon' => 'ph:smartphone-bold', 'color' => '#eab308', 'fixed' => false],
                ['name' => 'Gas & Cooking', 'icon' => 'ph:fire-bold', 'color' => '#eab308', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Health & Wellness', 'icon' => 'ph:heart-beat-bold', 'color' => '#ef4444', 'fixed' => false,
            'children' => [
                ['name' => 'Doctor Visits', 'icon' => 'ph:stethoscope-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Medicine', 'icon' => 'ph:pill-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Traditional Therapy', 'icon' => 'ph:leaf-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Fitness & Gyms', 'icon' => 'ph:barbell-bold', 'color' => '#ef4444', 'fixed' => true],
                ['name' => 'Family Care', 'icon' => 'ph:people-bold', 'color' => '#ef4444', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Shopping', 'icon' => 'ph:bag-bold', 'color' => '#14b8a6', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon' => 'ph:shopping-cart-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Clothing', 'icon' => 'ph:shopping-bags-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Beauty & Grooming', 'icon' => 'ph:scissors-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Electronics', 'icon' => 'ph:device-mobile-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Household Items', 'icon' => 'ph:lamp-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Gifts & Festivals', 'icon' => 'ph:gifts-bold', 'color' => '#14b8a6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Education', 'icon' => 'ph:graduation-cap-bold', 'color' => '#6366f1', 'fixed' => false,
            'children' => [
                ['name' => 'Tuition', 'icon' => 'ph:book-open-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'University / School Fees', 'icon' => 'ph:student-bold', 'color' => '#6366f1', 'fixed' => true],
                ['name' => 'Books & Stationery', 'icon' => 'ph:books-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'Courses', 'icon' => 'ph:computer-tower-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'Workshops', 'icon' => 'ph:lightbulb-bold', 'color' => '#6366f1', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Entertainment & Leisure', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false,
            'children' => [
                ['name' => 'Games', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Streaming', 'icon' => 'ph:television-bold', 'color' => '#ec4899', 'fixed' => true],
                ['name' => 'Cinema & Shows', 'icon' => 'ph:television-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Hobbies', 'icon' => 'ph:paint-brush-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Travel & Tourism', 'icon' => 'ph:airplane-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Festivals / Events', 'icon' => 'ph:party-popper-bold', 'color' => '#ec4899', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Finance', 'icon' => 'ph:coins-bold', 'color' => '#0ea5e9', 'fixed' => true,
            'children' => [
                ['name' => 'Admin Fees', 'icon' => 'ph:wallet-bold', 'color' => '#0ea5e9', 'fixed' => false],
                ['name' => 'Taxes', 'icon' => 'ph:wallet-bold', 'color' => '#0ea5e9', 'fixed' => false],
                ['name' => 'Interest', 'icon' => 'ph:wallet-bold', 'color' => '#0ea5e9', 'fixed' => false],
                ['name' => 'Savings', 'icon' => 'ph:wallet-bold', 'color' => '#0ea5e9', 'fixed' => true],
                ['name' => 'Loans & Repayments', 'icon' => 'ph:receipt-bold', 'color' => '#0ea5e9', 'fixed' => true],
                ['name' => 'Insurance', 'icon' => 'ph:shield-check-bold', 'color' => '#0ea5e9', 'fixed' => true],
                ['name' => 'Investments', 'icon' => 'ph:chart-line-bold', 'color' => '#0ea5e9', 'fixed' => false],
                ['name' => 'Charity & Donations', 'icon' => 'ph:heart-plus-bold', 'color' => '#0ea5e9', 'fixed' => false],
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
                'order' => $topOrder,
                'decorations' => [
                    'icon' => $group['icon'],
                    'color' => $group['color'],
                ],
                'is_fixed_cost' => $group['fixed'],
            ]);

            foreach ($group['children'] as $childIndex => $child) {
                $childOrder = sprintf('0.%03d', 100 + ($index * 90) + $childIndex + 1);
                Category::create([
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'type' => $group['name'] === 'Income' ? 'input' : 'output',
                    'order' => $childOrder,
                    'decorations' => [
                        'icon' => $child['icon'],
                        'color' => $child['color'],
                    ],
                    'is_fixed_cost' => $child['fixed'],
                ]);
            }
        }
    }
}
