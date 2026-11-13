<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\VehicleRepository;

/** Own fleet + drivers, insurance / permit expiry and upcoming duties. */
final class VehicleController extends Controller
{
    private const FIELDS = ['name', 'type', 'registration_no', 'seats', 'driver_name', 'driver_phone', 'insurance_expiry', 'permit_expiry', 'notes'];

    public function index(): never
    {
        $type = (string) $this->request->query('type', '');
        $active = (string) $this->request->query('active', '');
        $f = [
            'q' => mb_substr(trim((string) $this->request->query('q', '')), 0, 100),
            'type' => isset(VehicleRepository::TYPES[$type]) ? $type : '',
            'active' => in_array($active, ['0', '1'], true) ? $active : '',
            'docs' => $this->request->query('docs', '') === 'attention' ? 'attention' : '',
        ];
        $page = (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

        $this->view('admin/vehicles/index', [
            'title' => 'Vehicles & drivers',
            'pageTitle' => 'Vehicles & drivers',
            'pretitle' => 'Operations',
            'f' => $f,
            'result' => VehicleRepository::paginate($f, $page),
            'attention' => VehicleRepository::attentionCount(),
            'assignments' => VehicleRepository::nextAssignments(null, 15),
            'pageActions' => '<a href="' . e(url('/vehicles/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add vehicle</a>',
        ]);
    }

    public function create(): never
    {
        $this->form(null);
    }

    public function edit(string $id): never
    {
        $this->form($this->findOr404($this->id($id)));
    }

    public function store(): never
    {
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/vehicles/create', $errors);
        }
        $id = VehicleRepository::create($data);
        Activity::log('created', 'vehicle', $id, 'Added vehicle ' . $data['name']);
        Flash::success($data['name'] . ' has been added to your fleet.');
        $this->redirect('/vehicles');
    }

    public function update(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/vehicles/' . (int) $row['id'] . '/edit', $errors);
        }
        VehicleRepository::update((int) $row['id'], $data);
        Activity::log('updated', 'vehicle', (int) $row['id'], 'Updated vehicle ' . $data['name']);
        Flash::success('Changes to ' . $data['name'] . ' have been saved.');
        $this->redirect('/vehicles');
    }

    public function destroy(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        $used = VehicleRepository::usageCount((int) $row['id']);
        if ($used > 0) {
            Flash::error($row['name'] . ' is assigned to ' . $used . ' booking service' . ($used === 1 ? '' : 's')
                . ', so it cannot be deleted. Mark it inactive instead — it keeps its trip history but will not be offered for new bookings.');
            $this->redirect('/vehicles/' . (int) $row['id'] . '/edit');
        }
        VehicleRepository::delete((int) $row['id']);
        Activity::log('deleted', 'vehicle', (int) $row['id'], 'Deleted vehicle ' . $row['name']);
        Flash::success($row['name'] . ' has been deleted.');
        $this->redirect('/vehicles');
    }

    public function toggle(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        VehicleRepository::toggle((int) $row['id']);
        $nowActive = (int) $row['is_active'] !== 1;
        Activity::log('updated', 'vehicle', (int) $row['id'], ($nowActive ? 'Activated' : 'Deactivated') . ' vehicle ' . $row['name']);
        Flash::success($row['name'] . ($nowActive ? ' is active again.' : ' is now inactive — it will not be offered for new bookings.'));
        $back = (string) $this->request->input('back', '');
        $this->redirect(preg_match('#^/vehicles(\?[A-Za-z0-9=&%_.+\-]*)?$#', $back) ? $back : '/vehicles');
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = VehicleRepository::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    /** @param array<string,mixed>|null $row */
    private function form(?array $row): never
    {
        $this->view('admin/vehicles/form', [
            'title' => $row ? 'Edit vehicle' : 'Add vehicle',
            'pageTitle' => $row ? 'Edit ' . $row['name'] : 'Add a vehicle',
            'pretitle' => 'Vehicles & drivers',
            'vehicle' => $row,
            'assignments' => $row ? VehicleRepository::nextAssignments((int) $row['id'], 10) : [],
            'usage' => $row ? VehicleRepository::usageCount((int) $row['id']) : 0,
        ]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function validateInput(): array
    {
        $in = [];
        foreach (self::FIELDS as $k) {
            $v = $this->request->input($k, '');
            $in[$k] = is_string($v) || is_int($v) ? trim((string) $v) : '';
        }
        $in['registration_no'] = strtoupper(preg_replace('/\s+/', ' ', $in['registration_no']) ?? '');

        $v = Validator::make($in, [
            'name' => 'required|string|max:120',
            'type' => 'required|in:' . implode(',', array_keys(VehicleRepository::TYPES)),
            'registration_no' => 'nullable|string|max:30',
            'seats' => 'required|int|min:1|max:80',
            'driver_name' => 'nullable|string|max:120',
            'driver_phone' => 'nullable|phone|max:30',
            'insurance_expiry' => 'nullable|string|max:10',
            'permit_expiry' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:2000',
        ], [
            'name' => 'Vehicle name',
            'registration_no' => 'Registration number',
            'seats' => 'Seats',
            'driver_phone' => 'Driver phone',
        ]);
        $errors = $v->errors();
        foreach (['insurance_expiry' => 'Insurance expiry', 'permit_expiry' => 'Permit expiry'] as $k => $label) {
            if (!isset($errors[$k]) && $in[$k] !== '' && !self::validDate($in[$k])) {
                $errors[$k] = $label . ' must be a valid date.';
            }
        }
        if ($errors !== []) {
            return [[], $errors];
        }
        $c = $v->validated();
        return [[
            'name' => $c['name'],
            'type' => $c['type'],
            'registration_no' => $c['registration_no'],
            'seats' => (int) $c['seats'],
            'driver_name' => $c['driver_name'],
            'driver_phone' => $c['driver_phone'],
            'insurance_expiry' => $c['insurance_expiry'],
            'permit_expiry' => $c['permit_expiry'],
            'notes' => $c['notes'],
            'is_active' => in_array($this->request->input('is_active'), ['1', 'on', 1], true) ? 1 : 0,
        ], []];
    }

    private static function validDate(string $d): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }
}
