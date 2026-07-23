<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingSuccessData;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingDraftStore;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingSubmissionTokenStore;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingSuccessTokenStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Thin by design (ADR-029): receives a Request, reads/writes the
 * session-scoped Draft (ADR-029's own Controller responsibility), calls the
 * Booking Delivery Service for every ViewModel and for submission, and
 * returns a View. Never constructs a Booking Application/Domain type, never
 * calls a data-access class directly, never references `Booking\Domain` or
 * `Booking\Infrastructure`.
 */
final readonly class BookingController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private BookingDeliveryService $delivery,
        private BookingDraftStore $drafts,
        private BookingSuccessTokenStore $successTokens,
        private BookingSubmissionTokenStore $submissionTokens,
    ) {}

    public function landing(Request $request): View
    {
        return view('public-website.booking.landing', [
            'viewModel' => $this->delivery->landingViewModel($this->resolveContext($request)),
        ]);
    }

    public function service(Request $request): View|RedirectResponse
    {
        $context = $this->resolveContext($request);
        $viewModel = $this->delivery->serviceSelectionViewModel($context, $this->drafts->current());
        if ($viewModel === null) {
            return redirect()->route('public-website.booking.date');
        }

        return view('public-website.booking.service', ['viewModel' => $viewModel]);
    }

    public function updateService(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'service_id' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $serviceId = trim((string) ($validated['service_id'] ?? ''));
        $this->drafts->save($this->drafts->current()->withService($serviceId === '' ? null : $serviceId));

        return redirect()->route('public-website.booking.date');
    }

    public function date(Request $request): View
    {
        $context = $this->resolveContext($request);
        $draft = $this->drafts->current();

        return view('public-website.booking.date', [
            'viewModel' => $this->delivery->dateSelectionViewModel($context, $draft),
            'timeViewModel' => $this->delivery->timeSelectionViewModel($context, $draft),
        ]);
    }

    public function updateDate(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['nullable', 'date_format:H:i'],
        ])->validate();

        $draft = $this->drafts->current()->withDate($validated['appointment_date']);
        if (! empty($validated['appointment_time'])) {
            $this->drafts->save($draft->withTime($validated['appointment_time']));

            return redirect()->route('public-website.booking.details');
        }

        $this->drafts->save($draft);

        return redirect()->route('public-website.booking.date');
    }

    public function details(Request $request): View
    {
        $context = $this->resolveContext($request);
        $draft = $this->drafts->current();

        return view('public-website.booking.details', [
            'viewModel' => $this->delivery->patientDetailsViewModel(
                $context,
                $this->oldOrDraftString('patient_name', $draft->patientName),
                $this->oldOrDraftString('phone', $draft->phone),
                $this->oldOrDraftString('email', $draft->email),
                $this->oldOrDraftString('notes', $draft->notes),
                old('consent') !== null ? (bool) old('consent') : $draft->consent,
            ),
        ]);
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'patient_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:254'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ], [
            'patient_name.required' => 'Enter your full name.',
            'phone.required' => 'Enter a phone number we can reach you on.',
            'email.email' => 'Enter a valid email address.',
            'consent.accepted' => 'Please confirm you agree to be contacted about this booking.',
        ])->validate();

        $this->drafts->save($this->drafts->current()->withPatientDetails(
            $validated['patient_name'],
            $validated['phone'],
            $validated['email'] ?? null,
            $validated['notes'] ?? null,
            true,
        ));

        return redirect()->route('public-website.booking.review');
    }

    public function review(Request $request): View|RedirectResponse
    {
        $context = $this->resolveContext($request);
        $draft = $this->drafts->current();
        $viewModel = $this->delivery->reviewViewModel($context, $draft);
        if ($viewModel === null) {
            return redirect()->route('public-website.booking.start');
        }

        return view('public-website.booking.review', [
            'viewModel' => $viewModel,
            'submissionToken' => $this->submissionTokens->issue(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $context = $this->resolveContext($request);
        $draft = $this->drafts->current();

        if (! $this->submissionTokens->consume((string) $request->input('submission_token', ''))) {
            return redirect()->route('public-website.booking.review')
                ->withErrors(['submission' => 'Please review and resubmit.']);
        }

        try {
            $success = $this->delivery->submit((string) $context->websiteId, $draft);
        } catch (PublicBookingValidationException $exception) {
            return redirect()->route('public-website.booking.details')->withErrors(['patient_name' => $exception->getMessage()]);
        } catch (PublicBookingAvailabilityException $exception) {
            $this->drafts->save($draft->withoutTime());

            return redirect()->route('public-website.booking.date')->withErrors(['availability' => $exception->getMessage()]);
        } catch (PublicBookingBusinessRuleException $exception) {
            return redirect()->route('public-website.booking.review')->withErrors(['business_rule' => $exception->getMessage()]);
        } catch (PublicBookingInfrastructureException) {
            return redirect()->route('public-website.booking.review')->withErrors(['infrastructure' => 'Something went wrong on our end. Please try again.']);
        } catch (Throwable) {
            Log::error('Unexpected public booking submission failure.', [
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]);

            return redirect()->route('public-website.booking.review')->withErrors(['infrastructure' => 'Something went wrong on our end. Please try again.']);
        }

        $token = $this->successTokens->issue(new BookingSuccessData($success->reference, $success->status, $success->submittedAt));
        $this->drafts->clear();

        return redirect()->route('public-website.booking.success', ['token' => $token]);
    }

    private function oldOrDraftString(string $key, ?string $draftValue): ?string
    {
        $old = old($key);

        return is_string($old) ? $old : $draftValue;
    }

    private function resolveContext(Request $request): PublicSiteContext
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null || $context->websiteId === null) {
            throw new NotFoundHttpException;
        }

        return $context;
    }
}
