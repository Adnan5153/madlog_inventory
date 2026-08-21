<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreToolCheckinRequest;
use App\Http\Requests\Admin\StoreToolCheckoutRequest;
use App\Http\Requests\Admin\StoreToolRequest;
use App\Http\Requests\Admin\UpdateToolRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Scopes\WorkshopScope;
use App\Services\Inventory\ToolCheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ToolController extends Controller
{
    use AuthorizesRequests;
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function __construct(private readonly ToolCheckoutService $checkoutService) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $condition = $request->query('condition');
        $supplierId = $request->query('supplier_id');
        $binId = $request->query('bin_id');
        $holderId = $request->query('holder_id');
        $requiresMaintenance = $request->query('requires_maintenance');

        $tools = $this->buildToolsQuery(
            $request, $q, $categoryId, $status, $condition, $supplierId, $binId, $holderId, $requiresMaintenance,
        )->paginate(20)->withQueryString();

        return view('admin.tools.index', [
            'title' => 'Tools',
            'tools' => $tools,
            'q' => $q,
            'categoryId' => $categoryId,
            'status' => $status,
            'condition' => $condition,
            'supplierId' => $supplierId,
            'binId' => $binId,
            'holderId' => $holderId,
            'requiresMaintenance' => $requiresMaintenance,
            'categories' => $this->categoriesForForm($this->selectedWorkshopId($request) ?? auth()->user()?->workshop_id),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => ToolStatus::cases(),
            'conditions' => ToolCondition::cases(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $condition = $request->query('condition');
        $supplierId = $request->query('supplier_id');
        $binId = $request->query('bin_id');
        $holderId = $request->query('holder_id');
        $requiresMaintenance = $request->query('requires_maintenance');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.tools._row-template',
            singular: 'tool',
            builder: fn () => $this->buildToolsQuery(
                $request, $q, $categoryId, $status, $condition, $supplierId, $binId, $holderId, $requiresMaintenance,
            ),
        );
    }

    protected function singularNoun(): string
    {
        return 'tool';
    }

    private function buildToolsQuery(
        Request $request,
        string $q,
        mixed $categoryId,
        mixed $status,
        mixed $condition,
        mixed $supplierId,
        mixed $binId,
        mixed $holderId,
        mixed $requiresMaintenance,
    ): Builder {
        return Tool::query()
            ->with([
                'category:id,name',
                'supplier:id,name',
                'binLocation:id,code',
                'currentHolder:id,name',
            ])
            ->when($q !== '', function (Builder $qb) use ($q) {
                $qb->where(function (Builder $w) use ($q) {
                    $like = '%'.$q.'%';
                    $w->where('tools.name', 'like', $like)
                        ->orWhere('tools.tool_code', 'like', $like)
                        ->orWhere('tools.serial_number', 'like', $like)
                        ->orWhere('tools.barcode', 'like', $like)
                        ->orWhere('tools.qr_code', 'like', $like)
                        ->orWhere('tools.brand', 'like', $like)
                        ->orWhere('tools.model', 'like', $like)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like))
                        ->orWhereHas('currentHolder', fn ($u) => $u->where('name', 'like', $like))
                        ->orWhereHas('binLocation', fn ($b) => $b->where('code', 'like', $like));
                });
            })
            ->when($categoryId, fn (Builder $qb) => $qb->where('tools.category_id', $categoryId))
            ->when($status, fn (Builder $qb) => $qb->where('tools.status', $status))
            ->when($condition, fn (Builder $qb) => $qb->where('tools.condition', $condition))
            ->when($supplierId, fn (Builder $qb) => $qb->where('tools.supplier_id', $supplierId))
            ->when($binId, fn (Builder $qb) => $qb->where('tools.bin_id', $binId))
            ->when($holderId, fn (Builder $qb) => $qb->where('tools.current_holder_user_id', $holderId))
            ->when($requiresMaintenance === 'yes', fn (Builder $qb) => $qb->whereHas(
                'maintenanceRecords',
                fn (Builder $sub) => $sub->whereNotNull('next_due_at')->where('next_due_at', '<', now()),
            ))
            ->when($requiresMaintenance === 'no', fn (Builder $qb) => $qb->whereDoesntHave(
                'maintenanceRecords',
                fn (Builder $sub) => $sub->whereNotNull('next_due_at')->where('next_due_at', '<', now()),
            ))
            ->orderBy('tools.name')
            ->orderBy('tools.id');
    }

    private function binLocationsForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return BinLocation::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'zone', 'aisle', 'shelf']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return BinLocation::query()
                ->where('is_active', true)
                ->where('workshop_id', $workshopId)
                ->orderBy('code')
                ->get(['id', 'code', 'zone', 'aisle', 'shelf']);
        });
    }

    private function suppliersForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return Supplier::query()
                ->where('is_active', true)
                ->where('workshop_id', $workshopId)
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    }

    private function categoriesForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return ToolCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return ToolCategory::query()
                ->where('is_active', true)
                ->where('workshop_id', $workshopId)
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    }

    private function usersForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return User::query()->orderBy('name')->get(['id', 'name']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return User::query()->where('workshop_id', $workshopId)->orderBy('name')->get(['id', 'name']);
        });
    }

    public function create(Request $request): View
    {
        $pickedWorkshopId = $this->selectedWorkshopId($request)
            ?? auth()->user()?->workshop_id;

        return view('admin.tools.create', [
            'title' => 'New tool',
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $pickedWorkshopId,
            'binLocations' => $this->binLocationsForForm($pickedWorkshopId),
            'suppliers' => $this->suppliersForForm($pickedWorkshopId),
            'categories' => $this->categoriesForForm($pickedWorkshopId),
            'users' => $this->usersForForm($pickedWorkshopId),
            'statuses' => ToolStatus::cases(),
            'conditions' => ToolCondition::cases(),
        ]);
    }

    public function store(StoreToolRequest $request): RedirectResponse
    {
        $tool = Tool::create($request->validated());
        AuditLog::record('tool.created', $tool, $tool->only(['tool_code', 'name', 'workshop_id', 'status']));

        return redirect()->route('admin.tools.index')->with('status', 'Tool created.');
    }

    public function show(Tool $tool): View
    {
        $tool->load([
            'category', 'supplier', 'binLocation', 'currentHolder',
            'checkouts' => fn ($q) => $q->latest('checked_out_at')->limit(20),
            'checkouts.user:id,name', 'checkouts.issuedBy:id,name', 'checkouts.receivedBy:id,name',
            'maintenanceRecords' => fn ($q) => $q->latest('performed_at')->limit(20),
            'maintenanceRecords.performedBy:id,name',
        ]);

        $currentCheckout = $tool->currentCheckout;
        $currentCheckout?->load(['user:id,name', 'issuedBy:id,name']);

        return view('admin.tools.show', [
            'title' => $tool->name,
            'tool' => $tool,
            'currentCheckout' => $currentCheckout,
            'users' => $this->usersForForm($tool->workshop_id),
        ]);
    }

    public function edit(Tool $tool): View
    {
        return view('admin.tools.edit', [
            'title' => 'Edit tool',
            'tool' => $tool,
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $tool->workshop_id,
            'binLocations' => $this->binLocationsForForm($tool->workshop_id),
            'suppliers' => $this->suppliersForForm($tool->workshop_id),
            'categories' => $this->categoriesForForm($tool->workshop_id),
            'users' => $this->usersForForm($tool->workshop_id),
            'statuses' => ToolStatus::cases(),
            'conditions' => ToolCondition::cases(),
        ]);
    }

    public function update(UpdateToolRequest $request, Tool $tool): RedirectResponse
    {
        $before = $tool->only(['name', 'tool_code', 'status', 'condition', 'is_active']);
        $tool->update($request->validated());
        AuditLog::record('tool.updated', $tool, [
            'before' => $before,
            'after' => $tool->only(['name', 'tool_code', 'status', 'condition', 'is_active']),
        ]);

        return redirect()->route('admin.tools.index')->with('status', 'Tool updated.');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $this->authorize('delete', $tool);

        if ($tool->checkouts()->exists() || $tool->maintenanceRecords()->exists()) {
            throw ValidationException::withMessages([
                'tool' => 'Cannot delete a tool with checkout or maintenance history. Archive it instead.',
            ]);
        }

        AuditLog::record('tool.deleted', $tool, $tool->only(['tool_code', 'name']));
        $tool->delete();

        return redirect()->route('admin.tools.index')->with('status', 'Tool deleted.');
    }

    /**
     * POST /admin/tools/{tool}/checkout
     */
    public function checkout(StoreToolCheckoutRequest $request, Tool $tool): RedirectResponse
    {
        $this->checkoutService->checkout(
            tool: $tool,
            user: User::findOrFail((int) $request->input('user_id')),
            issuedBy: $request->user(),
            expectedReturn: $request->filled('expected_return_at') ? Carbon::parse($request->input('expected_return_at')) : null,
            purpose: $request->input('purpose'),
            notes: $request->input('notes'),
        );

        return redirect()->route('admin.tools.show', $tool)->with('status', 'Tool checked out.');
    }

    /**
     * POST /admin/tools/{tool}/checkin
     */
    public function checkin(StoreToolCheckinRequest $request, Tool $tool): RedirectResponse
    {
        $this->checkoutService->checkin(
            tool: $tool,
            receivedBy: User::findOrFail((int) $request->input('received_by')),
            conditionAtReturn: ToolCondition::from((string) $request->input('condition_at_return')),
            notes: $request->input('notes'),
        );

        return redirect()->route('admin.tools.show', $tool)->with('status', 'Tool checked in.');
    }
}
