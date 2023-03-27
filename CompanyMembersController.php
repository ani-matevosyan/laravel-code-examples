<?php

namespace App\Domains\Company\Controllers;

use App\Domains\Company\Models\CompanyMember;
use App\Domains\Permission\Helpers\PermissionsHelper;
use App\Domains\User\Models\User;
use App\Domains\Company\Filters\CompanyMembersFilter;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Requests\Company\CompanyInviteRequest;
use App\Domains\Company\Requests\Company\CompanyInviteReminderRequest;
use App\Domains\Company\Requests\CompanyMember\ApproveCompanyJoinRequest;
use App\Domains\Company\Requests\CompanyMember\DeclineCompanyJoinRequest;
use App\Domains\Company\Requests\CompanyMember\JoinCompanyRequest;
use App\Domains\Company\Requests\CompanyMember\CompanyMembersChangeStatusRequest;
use App\Domains\Company\Requests\CompanyMember\CompanyMembersFilterRequest;
use App\Domains\Company\Services\CompanyMembersInvitationServices;
use App\Domains\Company\Services\CompanyMembersServices;
use App\Domains\Company\Transformers\CompanyMemberTransformer;
use App\Helpers\NumberEncoderHelper;
use App\Http\Controllers\Controller;
use Dingo\Api\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyMembersController extends Controller
{
    /**
     * @api            {GET} /api/companies/{company_id}/members 1. Get Company Members
     * @apiName        GetCompanyMembers
     * @apiGroup       CompanyMembers
     * @apiDescription Shows Company Members info
     * @apiPermission  User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiUse         Authorization401
     * @apiUse         Forbidden403
     * @apiUse         Error404
     * @apiUse         CompanyMembersFilterRequest
     * @apiUse         CompanyMemberTransformer
     */
    public function index(
        Company $company,
        CompanyMembersFilterRequest $request,
        CompanyMembersFilter $filter,
        CompanyMembersServices $companyMembersServices
    ): Response {
        if (!$companyMembersServices->isCompanyMember($company)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }

        $companyMembers = $companyMembersServices->paginate($company, $filter, $request->validated());

        return $this->response->paginator($companyMembers, new CompanyMemberTransformer());
    }

    /**
     * @api           {PUT} /api/companies/{company_id}/leave 3. Leave Company
     * @apiName       CompanyLeave
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success leave company."
     * }
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function leave(
        Company $company,
        CompanyMembersServices $companyMembersServices
    ): Response {
        /** @var User $user */
        $user = Auth::user();
        if ($company->isOwner($user)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }

        $companyMembersServices->leave($company);

        return $this->response->array(
            ['message' => 'Successfully leaved the company.']
        );
    }

    /**
     * @api           {DELETE} /api/companies/{company_id}/members/{member_id} 3. Remove member
     * @apiName       RemoveMember
     * @apiGroup      RemoveMember
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Successfully leaved the company."
     * }
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function removeMember(
        Company $company,
        CompanyMember $member,
        CompanyMembersServices $companyMembersServices
    ): Response {
        /** @var User $user */
        $user = Auth::user();
        if (!$company->isOwner($user)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }
        if ($member->user_id == $company->owner_id ) {
            $this->response->errorForbidden("Company owner cannot be removed.");
        }

        $companyMembersServices->remove($member);

        return $this->response->array(
            ['message' => 'Successfully removed the member.']
        );
    }

    /**
     * @api           {PUT} /api/companies/{company_id}/member-status 2. Update Company Member Status
     * @apiName       CompanyMemberStatus
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiUse        CompanyMembersChangeStatusRequest
     * @apiUse        CompanyMemberTransformer
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function changeStatus(
        Company $company,
        CompanyMembersChangeStatusRequest $request,
        CompanyMembersServices $companyMembersServices
    ): Response {
        $userId = $request->get('user_id');
        if ($company->owner_id === $userId || !PermissionsHelper::can('company_manage_members', $company)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }

        $companyMembersServices->changeStatus($company, $request->validated());

        return $this->response->accepted();
    }


    /**
     * @api           {POST} /api/companies/{company_id}/request-join 4. Request Join To Company
     * @apiName       RequestJoinCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success send request to company."
     * }
     * @apiUse        BadRequest400
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function requestJoin(
        Company $company,
        CompanyMembersServices $companyMembersServices
    ): Response {
        if (!$companyMembersServices->ifLimitReached($company->id)) {
            $this->response->errorForbidden('Company Member adding limit reached.');
        }
        /** @var User $user */
        $user = Auth::user();
        if ($company->isOwner($user) || !is_null($company->current_member)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }
        $companyMembersServices->requestJoin($company, $user);

        return $this->response->array(
            ['message' => 'Success send request to company.'],
        );
    }

    /**
     * @api           {PUT} /api/companies/{company_id}/approve-request-join 5. Approve Request To Company
     * @apiName       ApproveRequestCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiUse        ApproveCompanyJoinRequest
     * @apiUse        CompanyMemberTransformer
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function approveRequestJoin(
        Company $company,
        ApproveCompanyJoinRequest $request,
        CompanyMembersServices $companyMembersServices
    ): Response {
        $member = $companyMembersServices->acceptRequestJoin($company, $request->validated());

        return $this->response->item($member, new CompanyMemberTransformer());
    }

    /**
     * @api           {PUT} /api/companies/{company_id}/decline-request-join 6. Decline Request To Company
     * @apiName       DeclineRequestCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success decline action."
     * }
     * @apiUse        DeclineCompanyJoinRequest
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function declineRequestJoin(
        Company $company,
        DeclineCompanyJoinRequest $request,
        CompanyMembersServices $companyMembersServices
    ): Response {
        $companyMembersServices->declineRequestJoin($company, $request->validated());

        return $this->response->array(
            ['message' => 'Success decline action.'],
        );
    }

    /**
     * @api           {POST} /api/companies/{company_id}/invite 7. Invite To Company
     * @apiName       InviteToCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success send invite."
     * }
     * @apiUse        CompanyInviteRequest
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function invite(
        Company $company,
        CompanyInviteRequest $request,
        CompanyMembersServices $companyMembersServices,
        CompanyMembersInvitationServices $companyMembersInvitationServices
    ): Response {
        if (!$companyMembersServices->isCompanyMember($company)) {
            $this->response->errorForbidden(MESSAGE_ACCESS_DENIED);
        }

        $members = $companyMembersInvitationServices->invite($company, $request->validated(), Auth::user());

        return $this->response->collection($members, new CompanyMemberTransformer());
    }

    /**
     * @api           {POST} /api/companies/{company_id}/send-reminder 7. Reminder for Invite To Company
     * @apiName       ReminderForInviteToCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success send reminder."
     * }
     * @apiUse        CompanyInviteReminderRequest
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     * @apiUse        Error404
     */
    public function reminder(
        Company $company,
        CompanyInviteReminderRequest $request,
        CompanyMembersServices $companyMembersServices
    ): Response {

        $companyMembersServices->sendReminder($company, $request->validated());

        return $this->response->array(
            ['message' => 'Successfully sent reminder.'],
        );
    }

    /**
     * @api           {PUT} /api/companies-join 8. Accept Invite To Company
     * @apiName       AcceptInviteToCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success join to company."
     * }
     * @apiUse        JoinCompanyRequest
     * @apiUse        Error404
     */
    public function acceptJoinToCompany(
        JoinCompanyRequest $request,
        CompanyMembersServices $companyMembersServices,
        NumberEncoderHelper $numberEncoderHelper
    ): Response {
        $code = $request->get('code');
        if (strlen($code) < 64) {
            /** @var User $user */
            $user = Auth::user();
            if (!$user) {
                $this->response->errorUnauthorized('Authorization required.');
            }

            $companyId = $numberEncoderHelper->decode($code);
            if (!$companyId) {
                $this->response->errorBadRequest('Code is invalid.');
            }

            /** @var Company $company */
            $company = Company::findOrFail($companyId);
            $companyMembersServices->joinWithInvitationCode($company, $user);
        } elseif (!$companyMembersServices->joinWithRequestCode($request->validated())) {
            $this->response->error('Sign Up Required.', 422);
        }

        return $this->response->array(
            ['message' => 'Success join to company.'],
        );
    }

    /**
     * @api           {PUT} /api/companies-decline 9. Decline Invite To Company
     * @apiName       DeclineInviteToCompany
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     * "message": "Success decline join to company."
     * }
     * @apiUse        JoinCompanyRequest
     * @apiUse        Error404
     */
    public function declineJoinToCompany(
        JoinCompanyRequest $request,
        CompanyMembersServices $companyMembersServices
    ): Response {
        $companyMembersServices->decline($request->validated());

        return $this->response->array(
            ['message' => 'Success decline join to company.'],
        );
    }

    /**
     * @api           {POST} /api/companies/{company}/generate-invitation-link 10. Generate Invitation Link
     * @apiParam      {Number} [event_id]
     * @apiName       GenerateInvitationLink
     * @apiGroup      CompanyMembers
     * @apiPermission User
     *
     * @apiHeader {String} Content-Type=application/json
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "code": string,
     *     "link": url
     * }
     * @apiUse        Authorization401
     * @apiUse        Forbidden403
     */
    public function generateInvitationLink(
        Request $request,
        Company $company,
        CompanyMembersServices $companyMembersServices
    ): Response {
        $eventId = $request->get('event_id');
        if ($eventId) {
            $code = $companyMembersServices->generateInvitationCode($eventId);

            return $this->response->array(
                [
                    'code' => $code,
                    'link' => $companyMembersServices->getEventInvitationLink($code),
                ]
            );
        }

        $code = $companyMembersServices->generateInvitationCode($company->getKey());

        return $this->response->array(
            [
                'code' => $code,
                'link' => $companyMembersServices->getCompanyInvitationLink($code),
            ]
        );
    }
}
