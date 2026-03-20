<x-global::pageheader :icon="'fa fa-life-ring'">
    <h5>Customer Support</h5>
    <h1 class="articleHeadline">New Support Ticket</h1>
</x-global::pageheader>

@include('supportcenter::partials.assetStyles')

<div class="maincontent">
    {!! $tpl->displayNotification() !!}

    <div class="maincontentinner supportcenter-shell">
        @if(count($supportProjects) === 0)
            <div class="alert alert-info">
                No support projects are currently available for your account.
            </div>
        @else
            <div class="supportcenter-panel">
                <form method="post" action="{{ BASE_URL }}/support-center/new" class="form-horizontal supportcenter-editor">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="control-label col-md-2" for="projectId">Project</label>
                                <div class="col-md-10">
                                    <select id="projectId" name="projectId" class="form-control">
                                        @foreach($supportProjects as $projectOption)
                                            <option value="{{ $projectOption['id'] }}" @selected(($selectedSupportProject['id'] ?? null) === $projectOption['id'])>
                                                {{ $projectOption['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-2" for="headline">Subject</label>
                                <div class="col-md-10">
                                    <input id="headline" type="text" name="headline" value="{{ old('headline') }}" class="form-control" required />
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-2" for="priority">Priority</label>
                                <div class="col-md-10">
                                    <select id="priority" name="priority" class="form-control">
                                        @foreach($priorities as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-2" for="description">Description</label>
                                <div class="col-md-10">
                                    <textarea id="description" name="description" rows="10" class="form-control tiptapComplex" required>{{ old('description') }}</textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-offset-2 col-md-10">
                                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                                    <a class="btn btn-default" href="{{ BASE_URL }}/support-center?projectId={{ $selectedSupportProject['id'] ?? '' }}">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.leantime && window.leantime.tiptapController) {
            window.leantime.tiptapController.initComplexEditor();
        }
    });
</script>
@endpush
