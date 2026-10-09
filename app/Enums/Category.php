<?php

namespace App\Enums;

use App\Data\DecorationData;

/**
 * The preset category catalog. Backed by string slugs; presentation
 * metadata lives on the cases (transcribed from the former seeder tree).
 * Declaration order is the display order.
 */
enum Category: string
{
    // Income — input
    case Salary = 'salary';
    case Freelance = 'freelance';
    case BusinessRevenue = 'business_revenue';
    case GrantsAndStipends = 'grants_and_stipends';
    case InvestmentReturns = 'investment_returns';
    case Dividends = 'dividends';
    case OtherIncome = 'other_income';
    case InitialBalance = 'initial_balance';

    // Finance
    case AdminFees = 'admin_fees';
    case Taxes = 'taxes';
    case Interest = 'interest';
    case Insurance = 'insurance';

    // Food & Drinks
    case DiningOut = 'dining_out';
    case SnacksAndDrinks = 'snacks_and_drinks';
    case CoffeeAndDesserts = 'coffee_and_desserts';
    case FoodTakeouts = 'food_takeouts';
    case BuffetFineDining = 'buffet_fine_dining';

    // Utilities
    case Electricity = 'electricity';
    case Water = 'water';
    case GasAndCooking = 'gas_and_cooking';
    case Internet = 'internet';
    case MobileAndPrepaid = 'mobile_and_prepaid';

    // Service & Housing
    case Rent = 'rent';
    case HomeMaintenance = 'home_maintenance';
    case CleaningServices = 'cleaning_services';
    case PropertyServices = 'property_services';

    // Shopping
    case Groceries = 'groceries';
    case Clothing = 'clothing';
    case BeautyAndGrooming = 'beauty_and_grooming';
    case Electronics = 'electronics';
    case HouseholdItems = 'household_items';

    // Entertainment & Leisure
    case Hobbies = 'hobbies';
    case Sports = 'sports';
    case Games = 'games';
    case CinemaAndShows = 'cinema_and_shows';
    case Streaming = 'streaming';
    case TravelAndTourism = 'travel_and_tourism';
    case FestivalsEvents = 'festivals_events';

    // Transport
    case Fuel = 'fuel';
    case PublicTransport = 'public_transport';
    case RideHailingTaxis = 'ride_hailing_taxis';
    case BusTrains = 'bus_trains';
    case FlightsFerries = 'flights_ferries';
    case TravelServices = 'travel_services';

    // Health & Wellness
    case Medicine = 'medicine';
    case DoctorVisits = 'doctor_visits';
    case TraditionalTherapy = 'traditional_therapy';
    case FitnessAndGyms = 'fitness_and_gyms';
    case FamilyCare = 'family_care';

    // Education
    case Tuition = 'tuition';
    case UniversitySchoolFees = 'university_school_fees';
    case BooksAndStationery = 'books_and_stationery';
    case CoursesAndWorkshops = 'courses_and_workshops';

    // Socials
    case FamilyAndFriends = 'family_and_friends';
    case Gifts = 'gifts';
    case CharityAndDonations = 'charity_and_donations';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Freelance => 'Freelance',
            self::BusinessRevenue => 'Business Revenue',
            self::GrantsAndStipends => 'Grants & Stipends',
            self::InvestmentReturns => 'Investment Returns',
            self::Dividends => 'Dividends',
            self::OtherIncome => 'Other Income',
            self::InitialBalance => 'Initial Balance',
            self::AdminFees => 'Admin Fees',
            self::Taxes => 'Taxes',
            self::Interest => 'Interest',
            self::Insurance => 'Insurance',
            self::DiningOut => 'Dining Out',
            self::SnacksAndDrinks => 'Snacks & Drinks',
            self::CoffeeAndDesserts => 'Coffee & Desserts',
            self::FoodTakeouts => 'Food Takeouts',
            self::BuffetFineDining => 'Buffet / Fine Dining',
            self::Electricity => 'Electricity',
            self::Water => 'Water',
            self::GasAndCooking => 'Gas & Cooking',
            self::Internet => 'Internet',
            self::MobileAndPrepaid => 'Mobile & Prepaid',
            self::Rent => 'Rent',
            self::HomeMaintenance => 'Home Maintenance',
            self::CleaningServices => 'Cleaning Services',
            self::PropertyServices => 'Property Services',
            self::Groceries => 'Groceries',
            self::Clothing => 'Clothing',
            self::BeautyAndGrooming => 'Beauty & Grooming',
            self::Electronics => 'Electronics',
            self::HouseholdItems => 'Household Items',
            self::Hobbies => 'Hobbies',
            self::Sports => 'Sports',
            self::Games => 'Games',
            self::CinemaAndShows => 'Cinema & Shows',
            self::Streaming => 'Streaming',
            self::TravelAndTourism => 'Travel & Tourism',
            self::FestivalsEvents => 'Festivals / Events',
            self::Fuel => 'Fuel',
            self::PublicTransport => 'Public Transport',
            self::RideHailingTaxis => 'Ride-hailing / Taxis',
            self::BusTrains => 'Bus / Trains',
            self::FlightsFerries => 'Flights / Ferries',
            self::TravelServices => 'Travel Services',
            self::Medicine => 'Medicine',
            self::DoctorVisits => 'Doctor Visits',
            self::TraditionalTherapy => 'Traditional Therapy',
            self::FitnessAndGyms => 'Fitness & Gyms',
            self::FamilyCare => 'Family Care',
            self::Tuition => 'Tuition',
            self::UniversitySchoolFees => 'University / School Fees',
            self::BooksAndStationery => 'Books & Stationery',
            self::CoursesAndWorkshops => 'Courses & Workshops',
            self::FamilyAndFriends => 'Family & Friends',
            self::Gifts => 'Gifts',
            self::CharityAndDonations => 'Charity & Donations',
        };
    }

    public function decorations(): DecorationData
    {
        return match ($this) {
            self::Salary => new DecorationData(icon: 'case', color: 'green-700'),
            self::Freelance => new DecorationData(icon: 'laptop', color: 'green-700'),
            self::BusinessRevenue => new DecorationData(icon: 'shop', color: 'green-700'),
            self::GrantsAndStipends => new DecorationData(icon: 'hand-shake', color: 'green-500'),
            self::InvestmentReturns => new DecorationData(icon: 'course-up', color: 'green-400'),
            self::Dividends => new DecorationData(icon: 'course-up', color: 'green-400'),
            self::OtherIncome => new DecorationData(icon: 'add-circle', color: 'green-200'),
            self::InitialBalance => new DecorationData(icon: 'course-up', color: 'green-200'),
            self::AdminFees => new DecorationData(icon: 'wallet', color: 'green-700'),
            self::Taxes => new DecorationData(icon: 'wallet', color: 'green-700'),
            self::Interest => new DecorationData(icon: 'wallet', color: 'green-600'),
            self::Insurance => new DecorationData(icon: 'shield-check', color: 'green-500'),
            self::DiningOut => new DecorationData(icon: 'donut', color: 'red-700'),
            self::SnacksAndDrinks => new DecorationData(icon: 'cup-hot', color: 'red-600'),
            self::CoffeeAndDesserts => new DecorationData(icon: 'mug', color: 'red-600'),
            self::FoodTakeouts => new DecorationData(icon: 'donut', color: 'red-500'),
            self::BuffetFineDining => new DecorationData(icon: 'chef-hat', color: 'red-400'),
            self::Electricity => new DecorationData(icon: 'lightning', color: 'amber-600'),
            self::Water => new DecorationData(icon: 'waterdrop', color: 'amber-500'),
            self::GasAndCooking => new DecorationData(icon: 'flame', color: 'amber-500'),
            self::Internet => new DecorationData(icon: 'wi-fi-high', color: 'amber-400'),
            self::MobileAndPrepaid => new DecorationData(icon: 'smartphone', color: 'amber-400'),
            self::Rent => new DecorationData(icon: 'key', color: 'yellow-600'),
            self::HomeMaintenance => new DecorationData(icon: 'settings-minimalistic', color: 'yellow-500'),
            self::CleaningServices => new DecorationData(icon: 'broom', color: 'yellow-400'),
            self::PropertyServices => new DecorationData(icon: 'buildings', color: 'yellow-400'),
            self::Groceries => new DecorationData(icon: 'cart-large', color: 'sky-600'),
            self::Clothing => new DecorationData(icon: 'bag-2', color: 'sky-500'),
            self::BeautyAndGrooming => new DecorationData(icon: 'scissors', color: 'sky-500'),
            self::Electronics => new DecorationData(icon: 'smartphone', color: 'sky-400'),
            self::HouseholdItems => new DecorationData(icon: 'lamp', color: 'sky-400'),
            self::Hobbies => new DecorationData(icon: 'paint-brush', color: 'cyan-600'),
            self::Sports => new DecorationData(icon: 'basketball', color: 'cyan-500'),
            self::Games => new DecorationData(icon: 'gamepad', color: 'cyan-500'),
            self::CinemaAndShows => new DecorationData(icon: 'tv', color: 'cyan-400'),
            self::Streaming => new DecorationData(icon: 'tv', color: 'cyan-400'),
            self::TravelAndTourism => new DecorationData(icon: 'plane', color: 'cyan-300'),
            self::FestivalsEvents => new DecorationData(icon: 'confetti', color: 'cyan-300'),
            self::Fuel => new DecorationData(icon: 'fuel', color: 'slate-900'),
            self::PublicTransport => new DecorationData(icon: 'bus', color: 'slate-800'),
            self::RideHailingTaxis => new DecorationData(icon: 'scooter', color: 'slate-800'),
            self::BusTrains => new DecorationData(icon: 'tram', color: 'slate-700'),
            self::FlightsFerries => new DecorationData(icon: 'plane', color: 'slate-700'),
            self::TravelServices => new DecorationData(icon: 'suitcase', color: 'slate-600'),
            self::Medicine => new DecorationData(icon: 'pill', color: 'rose-400'),
            self::DoctorVisits => new DecorationData(icon: 'stethoscope', color: 'rose-300'),
            self::TraditionalTherapy => new DecorationData(icon: 'leaf', color: 'rose-500'),
            self::FitnessAndGyms => new DecorationData(icon: 'dumbbell-large', color: 'rose-600'),
            self::FamilyCare => new DecorationData(icon: 'users-group-two-rounded', color: 'rose-700'),
            self::Tuition => new DecorationData(icon: 'book', color: 'lime-700'),
            self::UniversitySchoolFees => new DecorationData(icon: 'diploma', color: 'lime-600'),
            self::BooksAndStationery => new DecorationData(icon: 'library', color: 'lime-500'),
            self::CoursesAndWorkshops => new DecorationData(icon: 'server-square', color: 'lime-400'),
            self::FamilyAndFriends => new DecorationData(icon: 'users-group-two-rounded', color: 'violet-700'),
            self::Gifts => new DecorationData(icon: 'gift', color: 'violet-600'),
            self::CharityAndDonations => new DecorationData(icon: 'hand-heart', color: 'violet-500'),
        };
    }

    public function group(): CategoryGroup
    {
        return match ($this) {
            self::Salary,
            self::Freelance,
            self::BusinessRevenue,
            self::GrantsAndStipends,
            self::InvestmentReturns,
            self::Dividends,
            self::OtherIncome,
            self::InitialBalance => CategoryGroup::Income,

            self::AdminFees,
            self::Taxes,
            self::Interest,
            self::Insurance => CategoryGroup::Finance,

            self::DiningOut,
            self::SnacksAndDrinks,
            self::CoffeeAndDesserts,
            self::FoodTakeouts,
            self::BuffetFineDining => CategoryGroup::FoodAndDrinks,

            self::Electricity,
            self::Water,
            self::GasAndCooking,
            self::Internet,
            self::MobileAndPrepaid => CategoryGroup::Utilities,

            self::Rent,
            self::HomeMaintenance,
            self::CleaningServices,
            self::PropertyServices => CategoryGroup::ServiceAndHousing,

            self::Groceries,
            self::Clothing,
            self::BeautyAndGrooming,
            self::Electronics,
            self::HouseholdItems => CategoryGroup::Shopping,

            self::Hobbies,
            self::Sports,
            self::Games,
            self::CinemaAndShows,
            self::Streaming,
            self::TravelAndTourism,
            self::FestivalsEvents => CategoryGroup::EntertainmentAndLeisure,

            self::Fuel,
            self::PublicTransport,
            self::RideHailingTaxis,
            self::BusTrains,
            self::FlightsFerries,
            self::TravelServices => CategoryGroup::Transport,

            self::Medicine,
            self::DoctorVisits,
            self::TraditionalTherapy,
            self::FitnessAndGyms,
            self::FamilyCare => CategoryGroup::HealthAndWellness,

            self::Tuition,
            self::UniversitySchoolFees,
            self::BooksAndStationery,
            self::CoursesAndWorkshops => CategoryGroup::Education,
            self::FamilyAndFriends,
            self::Gifts,
            self::CharityAndDonations => CategoryGroup::Socials,
        };
    }

    public function isFixedCost(): bool
    {
        return match ($this) {
            self::Salary,
            self::InitialBalance,
            self::Insurance,
            self::Electricity,
            self::Water,
            self::Internet,
            self::Rent,
            self::Fuel,
            self::PublicTransport,
            self::FitnessAndGyms,
            self::Tuition,
            self::UniversitySchoolFees => true,
            default => false,
        };
    }

    /** Case values users may book — everything except the system-only opening balance. */
    public static function bookable(): array
    {
        return array_values(array_map(
            fn (self $category): string => $category->value,
            array_filter(self::cases(), fn (self $category): bool => $category !== self::InitialBalance),
        ));
    }
}
