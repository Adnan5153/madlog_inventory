{{--
    Bootstrap modals for the action endpoints (consume / replace / remove).
    Each modal is rendered server-side and activated via data-bs-toggle.
--}}

@php
    $consumable = $consumable ?? null;
    $isOpen = $consumable && $consumable->currentAssignment !== null;
@endphp

@if($consumable && $isOpen)
    {{-- Consume --}}
    <div class="modal fade" id="consumeModal" tabindex="-1" aria-labelledby="consumeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.equipment-consumables.consume', $consumable) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="consumeModalLabel">Record consumption</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Posts an Issue stock movement against the matching inventory bucket
                        and records a Consumed assignment row. Stock is decremented atomically.
                    </p>
                    @include('admin.equipment-consumables._event-form', [
                        'type' => 'consume',
                        'units' => $units,
                        'bins' => $bins,
                        'showResource' => false,
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-droplet me-1"></i> Record consumption
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Replace --}}
    <div class="modal fade" id="replaceModal" tabindex="-1" aria-labelledby="replaceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.equipment-consumables.replace', $consumable) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="replaceModalLabel">Replace consumable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Closes the current assignment and starts a new consumable on the same equipment.
                        The new resource must be selected from the dropdown below.
                    </p>
                    @include('admin.equipment-consumables._event-form', [
                        'type' => 'replace',
                        'units' => $units,
                        'bins' => $bins,
                        'parts' => $parts,
                        'batteries' => $batteries,
                        'lubricants' => $lubricants,
                        'showResource' => true,
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-arrow-left-right me-1"></i> Replace consumable
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Remove --}}
    <div class="modal fade" id="removeModal" tabindex="-1" aria-labelledby="removeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.equipment-consumables.remove', $consumable) }}" class="modal-content">
                @csrf
                <div class="modal-body">
                    <h5 class="modal-title mb-3" id="removeModalLabel">Remove consumable</h5>
                    <p class="text-muted small mb-3">
                        Marks the consumable as removed from the equipment. If you tick
                        "return to stock" with a positive quantity, a Return stock movement
                        restores the matching amount.
                    </p>
                    @include('admin.equipment-consumables._event-form', [
                        'type' => 'remove',
                        'units' => $units,
                        'bins' => $bins,
                        'showResource' => false,
                        'showReturn' => true,
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i> Remove consumable
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif