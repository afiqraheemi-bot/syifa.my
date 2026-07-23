<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application\ViewModels;

use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\AvailabilityChipViewData;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\DateSelectionViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\SuccessViewModel;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\TimeSelectionViewModel;
use PHPUnit\Framework\TestCase;

final class BookingViewModelsTest extends TestCase
{
    public function test_a_chip_is_tappable_only_when_available(): void
    {
        self::assertTrue((new AvailabilityChipViewData('2026-08-03', 'Aug 3', 'available', false))->tappable());
        self::assertFalse((new AvailabilityChipViewData('2026-08-03', 'Aug 3', 'unavailable', false))->tappable());
        self::assertFalse((new AvailabilityChipViewData('2026-08-03', 'Aug 3', 'unknown', false))->tappable());
    }

    public function test_date_selection_reports_no_available_date_when_every_chip_is_unavailable(): void
    {
        $viewModel = new DateSelectionViewModel(2, 4, [
            new AvailabilityChipViewData('2026-08-03', 'Aug 3', 'unavailable', false),
            new AvailabilityChipViewData('2026-08-04', 'Aug 4', 'unknown', false),
        ], null);

        self::assertFalse($viewModel->hasAnyAvailableDate());
    }

    public function test_date_selection_reports_an_available_date_when_at_least_one_chip_is(): void
    {
        $viewModel = new DateSelectionViewModel(2, 4, [
            new AvailabilityChipViewData('2026-08-03', 'Aug 3', 'unavailable', false),
            new AvailabilityChipViewData('2026-08-04', 'Aug 4', 'available', false),
        ], null);

        self::assertTrue($viewModel->hasAnyAvailableDate());
    }

    public function test_time_selection_reports_no_available_times_when_every_chip_is_unavailable(): void
    {
        $viewModel = new TimeSelectionViewModel(2, 4, '2026-08-03', [
            new AvailabilityChipViewData('09:00', '9:00 AM', 'unavailable', false),
        ], null);

        self::assertTrue($viewModel->hasNoAvailableTimes());
    }

    public function test_success_view_model_has_no_booking_id_property(): void
    {
        $viewModel = new SuccessViewModel('BOOK-STUB-ABC', 'received', 'now', null);

        self::assertFalse(property_exists($viewModel, 'bookingId'));
    }
}
