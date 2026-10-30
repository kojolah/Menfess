<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ContentFilterException;
use App\Models\ContentFilterRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentFilterController extends Controller
{
    public function index(): View
    {
        return view('admin.content-filters', [
            'rules' => ContentFilterRule::orderBy('category')->orderBy('phrase')->paginate(30, ['*'], 'rules'),
            'exceptions' => ContentFilterException::orderBy('phrase')->paginate(20, ['*'], 'exceptions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:160', 'unique:content_filter_rules,phrase'],
            'category' => ['required', Rule::in(['profanity', 'insult', 'sexual_18', 'other'])],
            'action' => ['required', Rule::in(['censor', 'review'])],
        ]);
        $rule = ContentFilterRule::create([...$validated, 'created_by' => $request->user()->id, 'is_active' => true]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.rule_created', 'subject_type' => ContentFilterRule::class, 'subject_id' => $rule->id]);

        return back()->with('notice', 'Aturan filter ditambahkan.');
    }

    public function update(Request $request, ContentFilterRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:160', Rule::unique('content_filter_rules', 'phrase')->ignore($rule->id)],
            'category' => ['required', Rule::in(['profanity', 'insult', 'sexual_18', 'other'])],
            'action' => ['required', Rule::in(['censor', 'review'])],
            'is_active' => ['required', 'boolean'],
        ]);
        $rule->update($validated);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.rule_updated', 'subject_type' => ContentFilterRule::class, 'subject_id' => $rule->id]);

        return back()->with('notice', 'Aturan filter diperbarui.');
    }

    public function destroy(Request $request, ContentFilterRule $rule): RedirectResponse
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.rule_deleted', 'subject_type' => ContentFilterRule::class, 'subject_id' => $rule->id]);
        $rule->delete();

        return back()->with('notice', 'Aturan filter dihapus.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:160', 'unique:content_filter_exceptions,phrase'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        ContentFilterException::create([...$validated, 'created_by' => $request->user()->id, 'is_active' => true]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.exception_created']);

        return back()->with('notice', 'Pengecualian ditambahkan.');
    }

    public function updateException(Request $request, ContentFilterException $exception): RedirectResponse
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:160', Rule::unique('content_filter_exceptions', 'phrase')->ignore($exception->id)],
            'reason' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
        $exception->update($validated);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.exception_updated']);

        return back()->with('notice', 'Pengecualian diperbarui.');
    }

    public function destroyException(Request $request, ContentFilterException $exception): RedirectResponse
    {
        $exception->delete();
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'content_filter.exception_deleted']);

        return back()->with('notice', 'Pengecualian dihapus.');
    }
}
