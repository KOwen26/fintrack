<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
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
                ['name' => 'Investment Returns', 'icon' => 'ph:trend-up-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Other Income', 'icon' => 'ph:plus-circle-bold', 'color' => '#22c55e', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Food & Drinks', 'icon' => 'ph:fork-knife-bold', 'color' => '#f97316', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon' => 'ph:shopping-cart-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Dining Out', 'icon' => 'ph:hamburger-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Coffee & Snacks', 'icon' => 'ph:coffee-bold', 'color' => '#f97316', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Transport', 'icon' => 'ph:car-bold', 'color' => '#3b82f6', 'fixed' => false,
            'children' => [
                ['name' => 'Fuel', 'icon' => 'ph:gas-pump-bold', 'color' => '#3b82f6', 'fixed' => true],
                ['name' => 'Ride-hailing', 'icon' => 'ph:motorcycle-bold', 'color' => '#3b82f6', 'fixed' => false],
                ['name' => 'Parking', 'icon' => 'ph:parking-sign-bold', 'color' => '#3b82f6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Utilities', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true,
            'children' => [
                ['name' => 'Electricity', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Internet', 'icon' => 'ph:wifi-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Water', 'icon' => 'ph:drop-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Phone', 'icon' => 'ph:phone-bold', 'color' => '#eab308', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Housing', 'icon' => 'ph:house-bold', 'color' => '#8b5cf6', 'fixed' => true,
            'children' => [
                ['name' => 'Rent', 'icon' => 'ph:key-bold', 'color' => '#8b5cf6', 'fixed' => true],
                ['name' => 'Home Maintenance', 'icon' => 'ph:wrench-bold', 'color' => '#8b5cf6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Health', 'icon' => 'ph:heart-beat-bold', 'color' => '#ef4444', 'fixed' => false,
            'children' => [
                ['name' => 'Doctor', 'icon' => 'ph:stethoscope-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Medicine', 'icon' => 'ph:pill-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Gym', 'icon' => 'ph:barbell-bold', 'color' => '#ef4444', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Entertainment', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false,
            'children' => [
                ['name' => 'Streaming', 'icon' => 'ph:television-bold', 'color' => '#ec4899', 'fixed' => true],
                ['name' => 'Games', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Hobbies', 'icon' => 'ph:paint-brush-bold', 'color' => '#ec4899', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Shopping', 'icon' => 'ph:bag-bold', 'color' => '#14b8a6', 'fixed' => false,
            'children' => [
                ['name' => 'Clothing', 'icon' => 'ph:t-shirt-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Electronics', 'icon' => 'ph:device-mobile-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Household Items', 'icon' => 'ph:lamp-bold', 'color' => '#14b8a6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Education', 'icon' => 'ph:graduation-cap-bold', 'color' => '#6366f1', 'fixed' => false,
            'children' => [
                ['name' => 'Courses', 'icon' => 'ph:book-open-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'Books', 'icon' => 'ph:books-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'School Fees', 'icon' => 'ph:student-bold', 'color' => '#6366f1', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Other Expense', 'icon' => 'ph:dots-three-circle-bold', 'color' => '#6b7280', 'fixed' => false,
            'children' => [],
        ],
    ];

    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $this->seedForUser($user);
    }

    public function seedForUser(User $user): void
    {
        foreach ($this->structure as $group) {
            $parent = Category::create([
                'user_id' => $user->id,
                'parent_id' => null,
                'name' => $group['name'],
                'icon' => $group['icon'],
                'color' => $group['color'],
                'is_fixed_cost' => $group['fixed'],
            ]);

            foreach ($group['children'] as $child) {
                Category::create([
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'color' => $child['color'],
                    'is_fixed_cost' => $child['fixed'],
                ]);
            }
        }
    }
}
