@php
    $plan = $delivery['plan'];
    $entity = $delivery['entity'];
    $seo = $plan->seoPlan();
    $slug = is_array($seo) && isset($seo['slug']) ? $seo['slug'] : null;
@endphp
<aside class="cip-card space-y-4 text-sm">
    <h2 class="font-[family-name:var(--font-display)] text-lg font-bold">Delivery</h2>

    <dl class="space-y-2">
        <div><dt class="font-semibold">Entity</dt><dd>{{ $plan->entityLabel() ?: '—' }} @if($plan->entityId())<span class="text-slate-500">({{ $plan->entityId() }})</span>@endif</dd></div>
        <div><dt class="font-semibold">Role</dt><dd>{{ $plan->contentRole() ?: '—' }}</dd></div>
        <div><dt class="font-semibold">CI action</dt><dd><span class="cip-badge">{{ $plan->action() }}</span></dd></div>
        <div><dt class="font-semibold">Canonical target</dt><dd class="break-all">{{ $plan->canonicalTargetUrl() ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Slug</dt><dd>{{ $slug ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Hub target</dt>
            <dd class="break-all">
                @if($plan->parentHubUrl())
                    {{ $plan->parentHubLabel() ?: $plan->parentHubEntityId() }} — {{ $plan->parentHubUrl() }}
                @else
                    —
                @endif
            </dd>
        </div>
        <div><dt class="font-semibold">Hub member</dt><dd>{{ $entity && $entity->hub_member ? 'yes' : 'no' }}</dd></div>
        <div><dt class="font-semibold">Hub eligibility</dt><dd>{{ $delivery['hub_eligibility_label'] }}</dd></div>
        @if($delivery['hub_exclusion_reason'])
            <div><dt class="font-semibold">Hub exclusion</dt><dd class="text-amber-800">{{ $delivery['hub_exclusion_reason'] }}</dd></div>
        @endif
    </dl>

    <div class="space-y-2 border-t border-slate-200 pt-3">
        <h3 class="font-semibold">Actions</h3>

        @if($delivery['can_download_package'])
            <a class="cip-btn-secondary block text-center" href="{{ route('app.delivery.package', $announcement) }}">Download Publish Package</a>
        @else
            <p class="text-slate-600">Publish package unavailable: {{ $delivery['package_unavailable_reason'] }}</p>
        @endif

        @if($delivery['can_create_wordpress_draft'])
            <form method="POST" action="{{ route('app.delivery.wordpress-draft', $announcement) }}" onsubmit="return confirm('Create a WordPress DRAFT only. No publish will occur.')">
                @csrf
                <button class="cip-btn w-full" type="submit">Create WordPress Draft</button>
            </form>
        @elseif($plan->action() === \App\Modules\Intelligence\Domain\ContentIntelligencePlan::ACTION_UPDATE_EXISTING)
            <p class="text-slate-600">WordPress draft not available for update_existing. Use the publish package and update the live article manually.</p>
        @elseif($plan->action() === \App\Modules\Intelligence\Domain\ContentIntelligencePlan::ACTION_CREATE_NEW && ! $delivery['wordpress_available'])
            <p class="text-slate-600">WordPress connector unavailable ({{ $delivery['wordpress_unavailable_reason'] }}).</p>
        @endif

        @if($delivery['can_release_to_hub'])
            <form class="space-y-2 border border-slate-200 p-3" method="POST" action="{{ route('app.delivery.hub-release', $announcement) }}">
                @csrf
                <p class="text-xs text-slate-600">Explicit Hub release. Choose lifecycle — expired deadlines do not auto-set in_progress.</p>
                <label class="block">
                    <span class="font-semibold">Lifecycle status</span>
                    <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1" name="lifecycle_status" required>
                        @foreach($delivery['lifecycle_options'] as $option)
                            <option value="{{ $option }}" @selected(old('lifecycle_status', $entity?->lifecycle_status) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="font-semibold">Public canonical satellite URL</span>
                    <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1" type="url" name="canonical_url" value="{{ old('canonical_url', $delivery['suggested_canonical_url']) }}" required placeholder="https://studymentor.gr/...">
                </label>
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="confirmed" value="1" required @checked(old('confirmed'))>
                    <span>I confirm this public URL and lifecycle for Hub release.</span>
                </label>
                <button class="cip-btn w-full" type="submit">Release to Hub</button>
            </form>
        @else
            <p class="text-slate-600">Hub release unavailable: {{ $delivery['hub_release_unavailable_reason'] }}</p>
        @endif
    </div>
</aside>
