@extends('include.app')

@section('header')
<script src="{{ asset('asset/script/events.js') }}"></script>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h4>{{ __('app.Events') }}</h4>
    </div>
    <div class="card-body">
        <table class="table table-striped w-100" id="eventsTable">
            <thead>
                <tr>
                    <th style="width: 200px"> {{ __('Event') }} </th>
                    <th> {{ __('Organizer') }} </th>
                    <th> {{ __('Start Date') }} </th>
                    <th> {{ __('End Date') }} </th>
                    <th> {{ __('Available Slots') }} </th>
                    <th> {{ __('Capacity') }} </th>
                    <th> {{ __('Status') }} </th>
                    <th style="text-align: right; width: 200px;"> {{ __('Action') }} </th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- View Event Modal -->
<div class="modal fade" id="viewEventModal" tabindex="-1" aria-labelledby="viewEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5 fw-normal" id="viewEventModalLabel">{{ __('View Event') }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Title') }}</label>
                    <p id="eventModalTitle"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Organizer') }}</label>
                    <p id="eventModalOrganizer"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Start Date') }}</label>
                    <p id="eventModalStartDate"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('End Date') }}</label>
                    <p id="eventModalEndDate"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Available Slots') }}</label>
                    <p id="eventModalAvailableSlots"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Capacity') }}</label>
                    <p id="eventModalCapacity"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <p id="eventModalStatus"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Description') }}</label>
                    <p id="eventModalDescription"></p>
                </div>
                <div class="form-group">
                    <label>{{ __('Image') }}</label>
                    <img id="eventModalImage" src="" class="img-fluid rounded" alt="Event Image">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection