@extends('layouts.guest')
@section('title', 'Select project — CIP')
@section('content')
<h1 class="mb-4 text-xl font-semibold">Select organization & project</h1>
<form method="POST" action="{{ route('app.context.store') }}" class="space-y-4">
    @csrf
    <div>
        <label class="cip-label" for="organization_id">Organization</label>
        <select class="cip-input" name="organization_id" id="organization_id" required>
            @foreach($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="cip-label" for="project_id">Project</label>
        <select class="cip-input" name="project_id" id="project_id" required>
            @foreach($projectsByOrg as $orgId => $projects)
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" data-org="{{ $orgId }}">{{ $project->name }}</option>
                @endforeach
            @endforeach
        </select>
    </div>
    <button class="cip-btn w-full" type="submit">Continue</button>
</form>
@endsection
