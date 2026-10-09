<?php

namespace App\Enums;

use App\Objects\Decoration;

enum CategoryGroup: string
{
    case Income = 'income';
    case Finance = 'finance';
    case FoodAndDrinks = 'food_and_drinks';
    case Utilities = 'utilities';
    case ServiceAndHousing = 'service_and_housing';
    case Shopping = 'shopping';
    case EntertainmentAndLeisure = 'entertainment_and_leisure';
    case Transport = 'transport';
    case HealthAndWellness = 'health_and_wellness';
    case Education = 'education';
    case Socials = 'socials';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Finance => 'Finance',
            self::FoodAndDrinks => 'Food & Drinks',
            self::Utilities => 'Utilities',
            self::ServiceAndHousing => 'Service & Housing',
            self::Shopping => 'Shopping',
            self::EntertainmentAndLeisure => 'Entertainment & Leisure',
            self::Transport => 'Transport',
            self::HealthAndWellness => 'Health & Wellness',
            self::Education => 'Education',
            self::Socials => 'Socials',
        };
    }

    public function decorations(): Decoration
    {
        return match ($this) {
            self::Income => new Decoration(icon: 'round-arrow-down', color: 'green-700'),
            self::Finance => new Decoration(icon: 'hand-money', color: 'green-700'),
            self::FoodAndDrinks => new Decoration(icon: 'plate', color: 'red-700'),
            self::Utilities => new Decoration(icon: 'lightning', color: 'amber-600'),
            self::ServiceAndHousing => new Decoration(icon: 'house', color: 'yellow-600'),
            self::Shopping => new Decoration(icon: 'bag', color: 'sky-600'),
            self::EntertainmentAndLeisure => new Decoration(icon: 'gamepad', color: 'cyan-600'),
            self::Transport => new Decoration(icon: 'wheel-angle', color: 'slate-900'),
            self::HealthAndWellness => new Decoration(icon: 'heart-pulse', color: 'rose-700'),
            self::Education => new Decoration(icon: 'square-academic-cap', color: 'lime-700'),
            self::Socials => new Decoration(icon: 'heart', color: 'violet-700'),
        };
    }

    public function flow(): Cashflow
    {
        return $this === self::Income ? Cashflow::Inflow : Cashflow::Outflow;
    }

    /** The bookable categories in this group, in display order. */
    public function children(): array
    {
        return array_values(array_filter(
            Category::cases(),
            fn (Category $category): bool => $category->group() === $this,
        ));
    }
}
