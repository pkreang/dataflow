<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class FormCalendarControllerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('forms.calendar'))->assertRedirect(route('login'));
        $this->get(route('forms.calendar.events'))->assertRedirect(route('login'));
    }

    public function test_employee_sees_only_own_submissions(): void
    {
        $employee = $this->makeRegularUser('cal-emp-'.uniqid().'@example.test');
        $other    = $this->makeRegularUser('cal-other-'.uniqid().'@example.test');
        $form     = $this->makeForm();

        $own   = $this->makeSubmission($form, $employee);
        $theirs = $this->makeSubmission($form, $other);

        $year  = now()->year;
        $month = now()->month;

        $resp = $this->actingAsWebSession($employee)
            ->getJson(route('forms.calendar.events', compact('year', 'month')))
            ->assertOk();

        $days = $resp->json('days');
        $allIds = collect($days)->flatten(1)->pluck('id')->all();

        $this->assertContains($own->id, $allIds);
        $this->assertNotContains($theirs->id, $allIds);
    }

    public function test_manager_sees_own_and_subordinate_submissions(): void
    {
        $manager  = $this->makeRegularUser('cal-mgr-'.uniqid().'@example.test');
        $employee = $this->makeRegularUser('cal-sub-'.uniqid().'@example.test');
        $employee->update(['manager_id' => $manager->id]);

        $unrelated = $this->makeRegularUser('cal-unrel-'.uniqid().'@example.test');
        $form = $this->makeForm();

        $mgrSub  = $this->makeSubmission($form, $manager);
        $empSub  = $this->makeSubmission($form, $employee);
        $otherSub = $this->makeSubmission($form, $unrelated);

        $year  = now()->year;
        $month = now()->month;

        $resp = $this->actingAsWebSession($manager)
            ->getJson(route('forms.calendar.events', compact('year', 'month')))
            ->assertOk();

        $allIds = collect($resp->json('days'))->flatten(1)->pluck('id')->all();

        $this->assertContains($mgrSub->id, $allIds);
        $this->assertContains($empSub->id, $allIds);
        $this->assertNotContains($otherSub->id, $allIds);
    }

    public function test_smart_date_plots_on_leave_period_not_created_at(): void
    {
        $employee = $this->makeRegularUser('cal-sd-'.uniqid().'@example.test');
        $form = $this->makeForm();

        // Submission created today but leave is next month
        $dateFrom = now()->addMonth()->startOfMonth()->toDateString(); // e.g. 2026-07-01
        $dateTo   = now()->addMonth()->startOfMonth()->addDays(2)->toDateString(); // e.g. 2026-07-03

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $employee->id,
            'payload' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'status'  => 'submitted',
        ]);

        // Query the LEAVE month — should see event on all 3 days
        $leaveYear  = now()->addMonth()->year;
        $leaveMonth = now()->addMonth()->month;

        $resp = $this->actingAsWebSession($employee)
            ->getJson(route('forms.calendar.events', ['year' => $leaveYear, 'month' => $leaveMonth]))
            ->assertOk();

        $days = $resp->json('days');
        $this->assertArrayHasKey($dateFrom, $days);
        $this->assertArrayHasKey($dateTo,   $days);
        $mid = now()->addMonth()->startOfMonth()->addDay()->toDateString();
        $this->assertArrayHasKey($mid, $days);

        // Query THIS month (created_at) — should NOT see the event
        $thisYear  = now()->year;
        $thisMonth = now()->month;

        // Only skip this check if dateFrom happens to fall in the current month too
        if ($leaveYear !== $thisYear || $leaveMonth !== $thisMonth) {
            $resp2 = $this->actingAsWebSession($employee)
                ->getJson(route('forms.calendar.events', ['year' => $thisYear, 'month' => $thisMonth]))
                ->assertOk();

            $days2  = $resp2->json('days');
            $allIds = collect($days2)->flatten(1)->pluck('id')->all();
            $this->assertNotContains($sub->id, $allIds);
        }
    }

    // ---- helpers ----

    private function makeForm(): DocumentForm
    {
        return DocumentForm::factory()->create([
            'form_key'      => 'cal_test_' . uniqid(),
            'document_type' => 'cal_test_' . uniqid(),
            'is_active'     => true,
        ]);
    }

    private function makeSubmission(DocumentForm $form, User $user): DocumentFormSubmission
    {
        return DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => [],
            'status'  => 'submitted',
        ]);
    }
}
