<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentChangeRequestApproveRequest;
use App\Http\Requests\AppointmentChangeRequestDeclineRequest;
use App\Models\AppointmentChangeRequest;
use App\Services\AppointmentManagementService;
use App\Services\SystemNotificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * DEPLA Phase 3D.2C/3D.3B - Admin/Staff review of patient change requests.
 *
 * Thin controller mirroring AppointmentManagementController: every state
 * transition is delegated to AppointmentManagementService (transaction +
 * appointment lock first, then request lock, in-transaction revalidation
 * and audit). Authorization is layered: the clinic middleware (auth +
 * admin|staff) plus each FormRequest's authorize(). Redirects go back()
 * so the reviewer keeps their calendar view/date/filters; DomainExceptions
 * surface as a safe session error (with errorRequestId + the owning
 * appointment id so the calendar reopens the details modal), never a 500.
 *
 * TYPE DISPATCH (Phase 3D.3B Step 2): both endpoints branch on the
 * bound request's type - cancellation -> the original Phase 3D.2C
 * service methods (behaviour byte-for-byte unchanged), reschedule ->
 * approveRescheduleRequest/declineRescheduleRequest (exact proposed
 * schedule applied on approval; decline mutates nothing), any other
 * type -> refused safely through the same session-error path (never a
 * 500, never a partial write). The FormRequests are shared verbatim:
 * optional 3-1000 review_note on approve, REQUIRED 3-1000 on decline.
 *
 * After each SUCCESSFUL review (post-commit) the patient's linked active
 * portal account is notified once - for a cancellation review by the
 * dedicated cancellation approved/declined notification, for a reschedule
 * review by the dedicated reschedule approved/declined notification
 * (Phase 3D.3B Step 3). Neither path also fires the generic
 * status-changed notification, so an approval never double-notifies, and
 * the decline explanations stay out of the notification payloads (portal
 * card only, Phase 3B.4 convention). A new reschedule SUBMISSION notifies
 * every active Admin/Staff account post-commit (K3, same convention as
 * 3D.2B cancellation submissions). Auto-resolution caused by a staff
 * cancel/complete (same transaction) sends NO extra notification: the
 * status notification for that cancel/complete already covers it.
 */
class AppointmentChangeRequestController extends Controller
{
    public function __construct(
        private readonly AppointmentManagementService $service,
        private readonly SystemNotificationService $notifications,
    ) {
    }

    public function approve(AppointmentChangeRequestApproveRequest $request, AppointmentChangeRequest $changeRequest): RedirectResponse
    {
        if ($changeRequest->type === AppointmentChangeRequest::TYPE_CANCELLATION) {
            try {
                $this->service->approveCancellationRequest(
                    $changeRequest,
                    $request->user(),
                    $request->validated('review_note')
                );
            } catch (DomainException $exception) {
                return $this->reviewError($changeRequest, $exception);
            }

            $this->notifications->notifyAppointmentCancellationApproved($changeRequest->fresh());

            return back()->with('success', 'Cancellation request approved. The appointment is now cancelled with the patient\'s reason.');
        }

        if ($changeRequest->type === AppointmentChangeRequest::TYPE_RESCHEDULE) {
            try {
                $this->service->approveRescheduleRequest(
                    $changeRequest,
                    $request->user(),
                    $request->validated('review_note')
                );
            } catch (DomainException $exception) {
                return $this->reviewError($changeRequest, $exception);
            }

            // Post-commit (Phase 3D.3B Step 3): the SINGLE patient
            // notification for a reschedule approval - no generic
            // status-changed notification is added (the appointment's
            // status never changed, only its schedule).
            $this->notifications->notifyAppointmentRescheduleApproved($changeRequest->fresh());

            return back()->with('success', 'Reschedule request approved. The appointment now follows the patient\'s proposed schedule.');
        }

        return $this->reviewError($changeRequest, new DomainException(
            'This change request cannot be reviewed - its request type is not recognized.'
        ));
    }

    public function decline(AppointmentChangeRequestDeclineRequest $request, AppointmentChangeRequest $changeRequest): RedirectResponse
    {
        if ($changeRequest->type === AppointmentChangeRequest::TYPE_CANCELLATION) {
            try {
                $this->service->declineCancellationRequest(
                    $changeRequest,
                    $request->user(),
                    $request->validated('review_note')
                );
            } catch (DomainException $exception) {
                return $this->reviewError($changeRequest, $exception);
            }

            $this->notifications->notifyAppointmentCancellationDeclined($changeRequest->fresh());

            return back()->with('success', 'Cancellation request declined. The appointment stays confirmed.');
        }

        if ($changeRequest->type === AppointmentChangeRequest::TYPE_RESCHEDULE) {
            try {
                $this->service->declineRescheduleRequest(
                    $changeRequest,
                    $request->user(),
                    $request->validated('review_note')
                );
            } catch (DomainException $exception) {
                return $this->reviewError($changeRequest, $exception);
            }

            // Post-commit (Phase 3D.3B Step 3): the SINGLE patient
            // notification for a reschedule decline. The decline
            // explanation stays out of the payload (portal card only,
            // later UI step) and no generic status-changed notification
            // is added (the appointment's status did not change).
            $this->notifications->notifyAppointmentRescheduleDeclined($changeRequest->fresh());

            return back()->with('success', 'Reschedule request declined. The appointment keeps its original confirmed schedule.');
        }

        return $this->reviewError($changeRequest, new DomainException(
            'This change request cannot be reviewed - its request type is not recognized.'
        ));
    }

    /**
     * Shared refusal shape (Phase 3D.2C contract): a session error keyed
     * 'change_request' plus the ids the calendar uses to reopen the
     * details modal - never a 500, never a partial write (the service
     * refuses before its first save inside the transaction).
     */
    private function reviewError(AppointmentChangeRequest $changeRequest, DomainException $exception): RedirectResponse
    {
        return back()
            ->with('errorRequestId', $changeRequest->id)
            ->with('errorAppointmentId', $changeRequest->appointment_id)
            // Phase 3D.3B Step 5: re-flash the submitted input so a stale
            // / already-reviewed / locked request keeps the staff member's
            // typed review note in the reopened modal instead of dropping it.
            ->withInput()
            ->withErrors(['change_request' => $exception->getMessage()]);
    }
}
