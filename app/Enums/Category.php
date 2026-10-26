<?php

namespace App\Enums;

use App\Objects\Decoration;

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
    case Bonus = 'bonus';
    case GrantsAndStipends = 'grants_and_stipends';
    case InvestmentIncome = 'investment_income';
    case OtherIncome = 'other_income';
    case InitialBalance = 'initial_balance';

    // Finance
    case AdminBankFees = 'admin_bank_fees';
    case InvestmentFees = 'investment_fees';
    case InterestExpense = 'interest_expense';
    case Taxes = 'taxes';
    case Insurance = 'insurance';

    // Food & Drinks
    case DiningOut = 'dining_out';
    case SnacksAndDrinks = 'snacks_and_drinks';
    case FoodTakeouts = 'food_takeouts';
    case BuffetFineDining = 'buffet_fine_dining';
    case GroceriesIngredient = 'groceries_ingredient';

    // Utilities
    case Electricity = 'electricity';
    case Water = 'water';
    case GasAndCooking = 'gas_and_cooking';
    case Internet = 'internet';
    case MobileAndPrepaid = 'mobile_and_prepaid';
    case WasteCollection = 'waste_collection';

    // Service & Housing
    case Rent = 'rent';
    case MortgagePayment = 'mortgage_payment';
    case BuildingFees = 'building_fees';
    case HomeMaintenance = 'home_maintenance';
    case CleaningServices = 'cleaning_services';
    case PropertyServices = 'property_services';
    case MovingExpenses = 'moving_expenses';

    // Shopping
    case GeneralShopping = 'general_shopping';
    case Clothing = 'clothing';
    case PersonalCare = 'personal_care';
    case JewelryAndAccessories = 'jewelry_and_accessories';
    case Electronics = 'electronics';
    case HomeGoods = 'home_goods';

    // Entertainment & Leisure
    case Hobbies = 'hobbies';
    case Sports = 'sports';
    case Games = 'games';
    case CinemaAndShows = 'cinema_and_shows';
    case Streaming = 'streaming';
    case BooksAndDigitalMedia = 'books_and_digital_media';
    case TravelActivities = 'travel_activities';
    case FestivalsEvents = 'festivals_events';

    // Transport & Travel
    case Fuel = 'fuel';
    case PublicTransport = 'public_transport';
    case RideHailingTaxis = 'ride_hailing_taxis';
    case ParkingAndTolls = 'parking_and_tolls';
    case VehicleMaintenance = 'vehicle_maintenance';
    case VehicleRegistration = 'vehicle_registration';
    case FlightsFerries = 'flights_ferries';
    case LongDistanceTravel = 'long_distance_travel';
    case Accommodation = 'accommodation';
    case TravelInsurance = 'travel_insurance';
    case TravelServices = 'travel_services';

    // Health & Wellness
    case Medicine = 'medicine';
    case DoctorVisits = 'doctor_visits';
    case Therapy = 'therapy';
    case MedicalOperation = 'medical_operation';
    case MedicalEquipment = 'medical_equipment';
    case TraditionalMedicine = 'traditional_medicine';
    case BeautyAndGrooming = 'beauty_and_grooming';
    case FitnessAndGyms = 'fitness_and_gyms';

    // Education
    case Tuition = 'tuition';
    case EducationFees = 'education_fees';
    case TutoringAndCourses = 'tutoring_courses';
    case BooksAndStationery = 'books_and_stationery';

    // Family & Children
    case Childcare = 'childcare';
    case ElderCare = 'elder_care';
    case FamilySupport = 'family_support';

    // Pets
    case PetFood = 'pet_food';
    case VeterinaryCare = 'veterinary_care';
    case PetGrooming = 'pet_grooming';

    // Socials & Giving
    case SocialActivities = 'social_activities';
    case Gifts = 'gifts';
    case SpecialOccasions = 'special_occasions';
    case CharityAndDonations = 'charity_and_donations';

    // Other
    case OtherExpenses = 'other_expenses';

    /** Case values users may book — everything except the system-only opening balance. */
    public static function bookable(): array
    {
        return array_values(array_map(
            fn (self $category): string => $category->value,
            array_filter(self::cases(), fn (self $category): bool => $category !== self::InitialBalance),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Freelance => 'Freelance',
            self::BusinessRevenue => 'Business Revenue',
            self::Bonus => 'Bonus',
            self::GrantsAndStipends => 'Grants & Stipends',
            self::InvestmentIncome => 'Investment Income',
            self::OtherIncome => 'Other Income',
            self::InitialBalance => 'Initial Balance',

            self::AdminBankFees => 'Admin / Bank Fees',
            self::InvestmentFees => 'Investment Fees',
            self::InterestExpense => 'Interest Expense',
            self::Taxes => 'Taxes',
            self::Insurance => 'Insurance',

            self::DiningOut => 'Dining Out',
            self::SnacksAndDrinks => 'Snacks & Drinks',
            self::FoodTakeouts => 'Food Takeouts',
            self::BuffetFineDining => 'Buffet / Fine Dining',
            self::GroceriesIngredient => 'Groceries & Ingredients',

            self::Electricity => 'Electricity',
            self::Water => 'Water',
            self::GasAndCooking => 'Gas & Cooking',
            self::Internet => 'Internet',
            self::MobileAndPrepaid => 'Mobile & Prepaid',
            self::WasteCollection => 'Waste Collection',
            self::Rent => 'Rent',
            self::MortgagePayment => 'Mortgage Payment',
            self::BuildingFees => 'Building Fees',
            self::HomeMaintenance => 'Home Maintenance',
            self::CleaningServices => 'Cleaning Services',
            self::PropertyServices => 'Property Services',
            self::MovingExpenses => 'Moving Expenses',
            self::GeneralShopping => 'General Shopping',
            self::Clothing => 'Clothing',
            self::PersonalCare => 'Personal Care',
            self::JewelryAndAccessories => 'Jewelry & Accessories',
            self::Electronics => 'Electronics',
            self::HomeGoods => 'Home Goods',
            self::Hobbies => 'Hobbies',
            self::Sports => 'Sports',
            self::Games => 'Games',
            self::CinemaAndShows => 'Cinema & Shows',
            self::Streaming => 'Streaming',
            self::BooksAndDigitalMedia => 'Books & Digital Media',
            self::TravelActivities => 'Travel Activities',
            self::FestivalsEvents => 'Festivals / Events',
            self::Fuel => 'Fuel',
            self::PublicTransport => 'Public Transport',
            self::RideHailingTaxis => 'Ride-hailing / Taxis',
            self::ParkingAndTolls => 'Parking & Tolls',
            self::VehicleMaintenance => 'Vehicle Maintenance',
            self::VehicleRegistration => 'Vehicle Registration',
            self::FlightsFerries => 'Flights / Ferries',
            self::LongDistanceTravel => 'Long-Distance Travel',
            self::Accommodation => 'Accommodation',
            self::TravelInsurance => 'Travel Insurance',
            self::TravelServices => 'Travel Services',
            self::Medicine => 'Medicine',
            self::DoctorVisits => 'Doctor Visits',
            self::Therapy => 'Therapy',
            self::MedicalOperation => 'Medical Operation',
            self::MedicalEquipment => 'Medical Equipment',
            self::TraditionalMedicine => 'Traditional Medicine',
            self::BeautyAndGrooming => 'Beauty & Grooming',
            self::FitnessAndGyms => 'Fitness & Gyms',
            self::Tuition => 'Tuition',
            self::EducationFees => 'Education Fees',
            self::TutoringAndCourses => 'Tutoring & Courses',
            self::BooksAndStationery => 'Books & Stationery',
            self::Childcare => 'Childcare',
            self::ElderCare => 'Elder Care',
            self::FamilySupport => 'Family Support',
            self::PetFood => 'Pet Food & Supplies',
            self::VeterinaryCare => 'Veterinary Care',
            self::PetGrooming => 'Pet Grooming',
            self::SocialActivities => 'Social Activities',
            self::Gifts => 'Gifts',
            self::SpecialOccasions => 'Special Occasions',
            self::CharityAndDonations => 'Charity & Donations',
            self::OtherExpenses => 'Other Expenses',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Salary => 'Fixed recurring pay from employment',
            self::Freelance => 'Income from contract or gig work outside regular employment',
            self::BusinessRevenue => 'Revenue from running a business or side hustle',
            self::Bonus => 'Variable extra pay such as bonuses, commissions, or seasonal handouts',
            self::GrantsAndStipends => 'Non-repayable awards such as scholarships, grants, or stipends',
            self::InvestmentIncome => 'Passive income from investments — interest from savings and deposits, plus dividends from stock or fund holdings',
            self::OtherIncome => 'Inflow that fits no other income category',
            self::InitialBalance => 'System-only opening row that seeds an account with its starting balance',

            self::AdminBankFees => 'Administrative and bank account charges — transfer fees, monthly maintenance, ATM, and card fees',
            self::InvestmentFees => 'Costs of investing — brokerage, fund management, and platform fees',
            self::InterestExpense => 'Interest paid on loans, credit cards, or financing',
            self::Taxes => 'Tax payments outside routine purchases',
            self::Insurance => 'Regular premiums for health, vehicle, property, or life coverage',

            self::DiningOut => 'Everyday meals out — routine restaurant, café, and food-stall visits',
            self::SnacksAndDrinks => 'Small purchases like coffee, snacks, and bottled drinks',
            self::FoodTakeouts => 'Delivery or takeaway orders eaten off-premises',
            self::BuffetFineDining => 'Planned premium eating — buffets, fine restaurants, and one-off food splurges; hosting a celebration belongs to Special Occasions',
            self::GroceriesIngredient => 'Groceries bought to cook at home, separated from general shopping',

            self::Electricity => 'Electricity bills',
            self::Water => 'Water bills',
            self::GasAndCooking => 'Cooking gas refills and piped gas bills',
            self::Internet => 'Home broadband and fixed internet plans',
            self::MobileAndPrepaid => 'Mobile plans, data packages, and prepaid credit',
            self::WasteCollection => 'Trash pickup fees and neighborhood waste dues',
            self::Rent => 'Periodic payments for a lived-in home',
            self::MortgagePayment => 'Home loan installments, principal and interest',
            self::BuildingFees => 'Compulsory building or estate dues such as maintenance and sinking funds',
            self::HomeMaintenance => 'Repairs and upkeep of the home itself',
            self::CleaningServices => 'Paid cleaning help for the household',
            self::PropertyServices => 'Professional services around property — agents, notary, legal',
            self::MovingExpenses => 'Costs of relocating — movers, transport, packing',
            self::GeneralShopping => 'Everyday purchases with no narrower shopping category — the shopping catch-all',
            self::Clothing => 'Apparel and footwear',
            self::PersonalCare => 'Self-care products for the daily routine — toiletries, skincare, and grooming supplies; salon and spa visits go to Beauty & Grooming',
            self::JewelryAndAccessories => 'Jewelry, watches, bags, and fashion accessories',
            self::Electronics => 'Devices and gadgets — phones, laptops, appliances',
            self::HomeGoods => 'Durable goods for the home — furniture, fixtures, decor, cookware, tools, and small appliances',
            self::Hobbies => 'Supplies and gear for personal hobbies and crafts',
            self::Sports => 'Active recreation — gear, court rentals, match fees',
            self::Games => 'Video games, board games, and in-game purchases',
            self::CinemaAndShows => 'Movie tickets, live shows, and concerts',
            self::Streaming => 'Recurring subscriptions for media platforms',
            self::BooksAndDigitalMedia => 'Recreational reading and digital entertainment media',
            self::TravelActivities => 'Attractions and activities during trips — tours, entrances, tickets',
            self::FestivalsEvents => 'Cultural festivals and public events',
            self::Fuel => 'Fuel for personal vehicles',
            self::PublicTransport => 'Everyday shared transit — bus, train, and metro fares for routine commutes',
            self::RideHailingTaxis => 'App-based rides and taxi fares',
            self::ParkingAndTolls => 'Parking charges and road tolls',
            self::VehicleMaintenance => 'Repairs and routine servicing of vehicles',
            self::VehicleRegistration => 'Registration, licensing, and related vehicle paperwork fees',
            self::FlightsFerries => 'Air and sea tickets between places',
            self::LongDistanceTravel => 'One-off intercity ground transport — intercity buses, trains, and shuttles (air and sea go to Flights / Ferries)',
            self::Accommodation => 'Hotels, hostels, resorts, and short-term stays',
            self::TravelInsurance => 'Insurance purchased for trips',
            self::TravelServices => 'Visa, passport, and other travel paperwork or agency services',
            self::Medicine => 'Prescription and over-the-counter medication',
            self::DoctorVisits => 'Consultations and routine medical checkups',
            self::Therapy => 'Mental health sessions — counseling, psychiatry, talk therapy',
            self::MedicalOperation => 'Surgical procedures and major medical interventions',
            self::MedicalEquipment => 'Medical devices and health aids like glasses or blood pressure monitors',
            self::TraditionalMedicine => 'Herbal remedies and traditional treatments',
            self::BeautyAndGrooming => 'Grooming and beauty services — salon visits, barber, spa treatments; products go to Personal Care',
            self::FitnessAndGyms => 'Gym memberships, fitness classes, and workout gear',
            self::Tuition => 'Recurring school payments — term, semester, or monthly tuition',
            self::EducationFees => 'One-time school charges — enrollment, exams, facilities, and graduation fees',
            self::TutoringAndCourses => 'Paid learning outside formal schooling — private tutoring, lessons, classes, workshops, and certifications',
            self::BooksAndStationery => 'Study supplies — textbooks, stationery, school materials',
            self::Childcare => 'Babysitting, daycare, and childcare services',
            self::ElderCare => 'Care expenses for elderly family members',
            self::FamilySupport => 'Recurring financial support given to family members',
            self::PetFood => 'Pet food and everyday pet supplies',
            self::VeterinaryCare => 'Vet visits, medication, and medical treatment for pets',
            self::PetGrooming => 'Grooming, pet sitting, and boarding for pets',
            self::SocialActivities => 'Social spending with friends when no specific category fits — venue fees, rounds, shared treats; the dinner goes to Dining Out, the movie to Cinema & Shows',
            self::Gifts => 'Presents for others — birthdays, holidays, tokens of appreciation',
            self::SpecialOccasions => 'Celebration expenses — birthdays, anniversaries, parties',
            self::CharityAndDonations => 'Donations to charities and causes, plus community dues and local contributions',
            self::OtherExpenses => 'Everyday and one-off outflows that fit no other category — errands, small purchases, rare leftovers',
        };
    }

    public function group(): CategoryGroup
    {
        return match ($this) {
            self::Salary,
            self::Freelance,
            self::BusinessRevenue,
            self::Bonus,
            self::GrantsAndStipends,
            self::InvestmentIncome,
            self::OtherIncome,
            self::InitialBalance => CategoryGroup::Income,

            self::AdminBankFees,
            self::InvestmentFees,
            self::InterestExpense,
            self::Taxes,
            self::Insurance => CategoryGroup::Finance,

            self::DiningOut,
            self::SnacksAndDrinks,
            self::FoodTakeouts,
            self::BuffetFineDining,
            self::GroceriesIngredient => CategoryGroup::FoodAndDrinks,

            self::Electricity,
            self::Water,
            self::GasAndCooking,
            self::Internet,
            self::MobileAndPrepaid,
            self::WasteCollection => CategoryGroup::Utilities,

            self::Rent,
            self::MortgagePayment,
            self::BuildingFees,
            self::HomeMaintenance,
            self::CleaningServices,
            self::PropertyServices,
            self::MovingExpenses => CategoryGroup::ServiceAndHousing,

            self::GeneralShopping,
            self::Clothing,
            self::PersonalCare,
            self::JewelryAndAccessories,
            self::Electronics,
            self::HomeGoods => CategoryGroup::Shopping,

            self::Hobbies,
            self::Sports,
            self::Games,
            self::CinemaAndShows,
            self::Streaming,
            self::BooksAndDigitalMedia,
            self::TravelActivities,
            self::FestivalsEvents => CategoryGroup::EntertainmentAndLeisure,

            self::Fuel,
            self::PublicTransport,
            self::RideHailingTaxis,
            self::ParkingAndTolls,
            self::VehicleMaintenance,
            self::VehicleRegistration,
            self::FlightsFerries,
            self::LongDistanceTravel,
            self::Accommodation,
            self::TravelInsurance,
            self::TravelServices => CategoryGroup::TransportTravel,

            self::Medicine,
            self::DoctorVisits,
            self::Therapy,
            self::MedicalOperation,
            self::MedicalEquipment,
            self::TraditionalMedicine,
            self::BeautyAndGrooming,
            self::FitnessAndGyms => CategoryGroup::HealthAndWellness,

            self::Tuition,
            self::EducationFees,
            self::TutoringAndCourses,
            self::BooksAndStationery => CategoryGroup::Education,

            self::Childcare,
            self::ElderCare,
            self::FamilySupport => CategoryGroup::FamilyAndChildren,

            self::PetFood,
            self::VeterinaryCare,
            self::PetGrooming => CategoryGroup::Pets,

            self::SocialActivities,
            self::Gifts,
            self::SpecialOccasions,
            self::CharityAndDonations => CategoryGroup::SocialsAndGiving,

            self::OtherExpenses => CategoryGroup::Other,
        };
    }

    public function decorations(): Decoration
    {
        return match ($this) {
            self::Salary => new Decoration(icon: 'case', color: 'green-700'),
            self::Freelance => new Decoration(icon: 'laptop', color: 'green-700'),
            self::BusinessRevenue => new Decoration(icon: 'shop', color: 'green-700'),
            self::Bonus => new Decoration(icon: 'money-bag', color: 'green-600'),
            self::GrantsAndStipends => new Decoration(icon: 'hand-shake', color: 'green-500'),
            self::InvestmentIncome => new Decoration(icon: 'course-up', color: 'green-400'),
            self::OtherIncome => new Decoration(icon: 'add-circle', color: 'green-200'),
            self::InitialBalance => new Decoration(icon: 'course-up', color: 'green-200'),
            self::AdminBankFees => new Decoration(icon: 'card', color: 'green-600'),
            self::InvestmentFees => new Decoration(icon: 'chart-2', color: 'green-500'),
            self::InterestExpense => new Decoration(icon: 'wallet', color: 'green-600'),
            self::Taxes => new Decoration(icon: 'wallet', color: 'green-700'),
            self::Insurance => new Decoration(icon: 'shield-check', color: 'green-500'),
            self::DiningOut => new Decoration(icon: 'donut', color: 'red-700'),
            self::SnacksAndDrinks => new Decoration(icon: 'cup-hot', color: 'red-600'),
            self::FoodTakeouts => new Decoration(icon: 'donut', color: 'red-500'),
            self::BuffetFineDining => new Decoration(icon: 'chef-hat', color: 'red-400'),
            self::GroceriesIngredient => new Decoration(icon: 'cart-large', color: 'red-600'),
            self::Electricity => new Decoration(icon: 'lightning', color: 'amber-600'),
            self::Water => new Decoration(icon: 'waterdrop', color: 'amber-500'),
            self::GasAndCooking => new Decoration(icon: 'flame', color: 'amber-500'),
            self::Internet => new Decoration(icon: 'wi-fi-high', color: 'amber-400'),
            self::MobileAndPrepaid => new Decoration(icon: 'smartphone', color: 'amber-400'),
            self::WasteCollection => new Decoration(icon: 'broom', color: 'amber-500'),
            self::Rent => new Decoration(icon: 'key', color: 'yellow-600'),
            self::MortgagePayment => new Decoration(icon: 'house', color: 'yellow-600'),
            self::BuildingFees => new Decoration(icon: 'buildings', color: 'yellow-500'),
            self::HomeMaintenance => new Decoration(icon: 'settings-minimalistic', color: 'yellow-500'),
            self::CleaningServices => new Decoration(icon: 'broom', color: 'yellow-400'),
            self::PropertyServices => new Decoration(icon: 'buildings', color: 'yellow-400'),
            self::MovingExpenses => new Decoration(icon: 'backpack', color: 'yellow-600'),
            self::GeneralShopping => new Decoration(icon: 'bag', color: 'sky-600'),
            self::Clothing => new Decoration(icon: 'bag-2', color: 'sky-500'),
            self::PersonalCare => new Decoration(icon: 'scissors', color: 'sky-500'),
            self::JewelryAndAccessories => new Decoration(icon: 'tag', color: 'sky-400'),
            self::Electronics => new Decoration(icon: 'smartphone', color: 'sky-400'),
            self::HomeGoods => new Decoration(icon: 'lamp', color: 'sky-400'),
            self::Hobbies => new Decoration(icon: 'paint-brush', color: 'cyan-600'),
            self::Sports => new Decoration(icon: 'basketball', color: 'cyan-500'),
            self::Games => new Decoration(icon: 'gamepad', color: 'cyan-500'),
            self::CinemaAndShows => new Decoration(icon: 'tv', color: 'cyan-400'),
            self::Streaming => new Decoration(icon: 'play-circle', color: 'cyan-400'),
            self::BooksAndDigitalMedia => new Decoration(icon: 'book-2', color: 'cyan-400'),
            self::TravelActivities => new Decoration(icon: 'earth', color: 'cyan-500'),
            self::FestivalsEvents => new Decoration(icon: 'confetti', color: 'cyan-300'),
            self::Fuel => new Decoration(icon: 'fuel', color: 'slate-900'),
            self::PublicTransport => new Decoration(icon: 'bus', color: 'slate-800'),
            self::RideHailingTaxis => new Decoration(icon: 'scooter', color: 'slate-800'),
            self::ParkingAndTolls => new Decoration(icon: 'wheel-angle', color: 'slate-700'),
            self::VehicleMaintenance => new Decoration(icon: 'toolbox', color: 'slate-700'),
            self::VehicleRegistration => new Decoration(icon: 'document-text', color: 'slate-600'),
            self::FlightsFerries => new Decoration(icon: 'plane', color: 'slate-700'),
            self::LongDistanceTravel => new Decoration(icon: 'bus', color: 'slate-700'),
            self::Accommodation => new Decoration(icon: 'buildings', color: 'slate-600'),
            self::TravelInsurance => new Decoration(icon: 'shield-check', color: 'slate-600'),
            self::TravelServices => new Decoration(icon: 'suitcase', color: 'slate-600'),
            self::Medicine => new Decoration(icon: 'pill', color: 'rose-400'),
            self::DoctorVisits => new Decoration(icon: 'stethoscope', color: 'rose-300'),
            self::Therapy => new Decoration(icon: 'chat-round', color: 'rose-500'),
            self::MedicalOperation => new Decoration(icon: 'heart-pulse', color: 'rose-700'),
            self::MedicalEquipment => new Decoration(icon: 'medical-kit', color: 'rose-600'),
            self::TraditionalMedicine => new Decoration(icon: 'leaf', color: 'rose-500'),
            self::BeautyAndGrooming => new Decoration(icon: 'scissors', color: 'rose-400'),
            self::FitnessAndGyms => new Decoration(icon: 'dumbbell-large', color: 'rose-600'),
            self::Tuition => new Decoration(icon: 'book', color: 'lime-700'),
            self::EducationFees => new Decoration(icon: 'diploma', color: 'lime-600'),
            self::TutoringAndCourses => new Decoration(icon: 'lightbulb', color: 'lime-500'),
            self::BooksAndStationery => new Decoration(icon: 'library', color: 'lime-500'),
            self::Childcare => new Decoration(icon: 'users-group-two-rounded', color: 'pink-600'),
            self::ElderCare => new Decoration(icon: 'heart-angle', color: 'pink-500'),
            self::FamilySupport => new Decoration(icon: 'hand-money', color: 'pink-400'),
            self::PetFood => new Decoration(icon: 'cart-large', color: 'orange-600'),
            self::VeterinaryCare => new Decoration(icon: 'stethoscope', color: 'orange-500'),
            self::PetGrooming => new Decoration(icon: 'scissors', color: 'orange-400'),
            self::SocialActivities => new Decoration(icon: 'users-group-two-rounded', color: 'violet-700'),
            self::Gifts => new Decoration(icon: 'gift', color: 'violet-600'),
            self::SpecialOccasions => new Decoration(icon: 'confetti', color: 'violet-500'),
            self::CharityAndDonations => new Decoration(icon: 'hand-heart', color: 'violet-500'),
            self::OtherExpenses => new Decoration(icon: 'dollar-minimalistic', color: 'slate-400'),
        };
    }
}
