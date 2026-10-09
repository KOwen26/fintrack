<?php

namespace App\Enums;

use App\Data\DecorationData;

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

    public function decorations(): DecorationData
    {
        return match ($this) {
            self::Income => new DecorationData(icon: 'round-arrow-down', color: 'green-700'),
            self::Finance => new DecorationData(icon: 'hand-money', color: 'green-700'),
            self::FoodAndDrinks => new DecorationData(icon: 'plate', color: 'red-700'),
            self::Utilities => new DecorationData(icon: 'lightning', color: 'amber-600'),
            self::ServiceAndHousing => new DecorationData(icon: 'house', color: 'yellow-600'),
            self::Shopping => new DecorationData(icon: 'bag', color: 'sky-600'),
            self::EntertainmentAndLeisure => new DecorationData(icon: 'gamepad', color: 'cyan-600'),
            self::Transport => new DecorationData(icon: 'wheel-angle', color: 'slate-900'),
            self::HealthAndWellness => new DecorationData(icon: 'heart-pulse', color: 'rose-700'),
            self::Education => new DecorationData(icon: 'square-academic-cap', color: 'lime-700'),
            self::Socials => new DecorationData(icon: 'heart', color: 'violet-700'),
        };
    }

    public function type(): CategoryType
    {
        return $this === self::Income ? CategoryType::Input : CategoryType::Output;
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
