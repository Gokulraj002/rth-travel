<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\VendorRepository;

/** Hotels & vendors: suppliers, their contacts, service history and what we owe them. */
final class VendorController extends Controller
{
    private const FIELDS = ['type', 'name', 'contact_person', 'phone', 'email', 'city', 'address', 'gstin', 'bank_details', 'rating', 'notes'];

    public function index(): never
    {
        $f = $this->filters();
        $page = (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

        $this->view('admin/vendors/index', [
            'title' => 'Hotels & vendors',
            'pageTitle' => 'Hotels & vendors',
            'pretitle' => 'Operations',
            'f' => $f,
            'result' => VendorRepository::paginate($f, $page),
            'typeCounts' => VendorRepository::typeCounts($f),
            'cities' => VendorRepository::cities(),
            'pageActions' => '<a href="' . e(url('/vendors/payables')) . '" class="btn btn-outline-secondary">' . icon('wallet') . ' Payables</a> '
                . '<a href="' . e(url('/vendors/create' . ($f['type'] !== '' ? '?type=' . rawurlencode($f['type']) : ''))) . '" class="btn btn-primary">' . icon('plus') . ' Add vendor</a>',
        ]);
    }

    public function payables(): never
    {
        $type = (string) $this->request->query('type', '');
        $type = isset(VendorRepository::TYPES[$type]) ? $type : '';
        $rows = VendorRepository::payables($type);
        $this->view('admin/vendors/payables', [
            'title' => 'Vendor payables',
            'pageTitle' => 'What we owe suppliers',
            'pretitle' => 'Hotels & vendors',
            'rows' => $rows,
            'type' => $type,
            'total' => array_sum(array_map(static fn (array $r): float => (float) $r['payable'], $rows)),
            'pageActions' => '<a href="' . e(url('/vendors')) . '" class="btn btn-ghost-secondary">' . icon('arrow-left') . ' All vendors</a>',
        ]);
    }

    public function show(string $id): never
    {
        $vendor = $this->findOr404($this->id($id));
        $this->view('admin/vendors/show', [
            'title' => (string) $vendor['name'],
            'pageTitle' => (string) $vendor['name'],
            'pretitle' => VendorRepository::typeLabel((string) $vendor['type']),
            'vendor' => $vendor,
            'services' => VendorRepository::services((int) $vendor['id']),
            'pageActions' => '<a href="' . e(url('/vendors/' . (int) $vendor['id'] . '/edit')) . '" class="btn btn-primary">' . icon('edit') . ' Edit</a>',
        ]);
    }

    public function create(): never
    {
        $type = (string) $this->request->query('type', 'hotel');
        $this->form(null, isset(VendorRepository::TYPES[$type]) ? $type : 'hotel');
    }

    public function edit(string $id): never
    {
        $this->form($this->findOr404($this->id($id)));
    }

    public function store(): never
    {
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/vendors/create', $errors);
        }
        $id = VendorRepository::create($data);
        Activity::log('created', 'vendor', $id, 'Added vendor ' . $data['name']);
        Flash::success($data['name'] . ' has been added to your vendors.');
        $this->redirect('/vendors/' . $id);
    }

    public function update(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/vendors/' . (int) $row['id'] . '/edit', $errors);
        }
        VendorRepository::update((int) $row['id'], $data);
        Activity::log('updated', 'vendor', (int) $row['id'], 'Updated vendor ' . $data['name']);
        Flash::success('Changes to ' . $data['name'] . ' have been saved.');
        $this->redirect('/vendors/' . (int) $row['id']);
    }

    public function destroy(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        $used = VendorRepository::usageCount((int) $row['id']);
        if ($used > 0) {
            Flash::error($row['name'] . ' is used in ' . $used . ' booking service' . ($used === 1 ? '' : 's')
                . ', so it cannot be deleted (that would break your booking costs). Mark it inactive instead — it will disappear from the pick lists but keep its history.');
            $this->redirect('/vendors/' . (int) $row['id']);
        }
        VendorRepository::delete((int) $row['id']);
        Activity::log('deleted', 'vendor', (int) $row['id'], 'Deleted vendor ' . $row['name']);
        Flash::success($row['name'] . ' has been deleted.');
        $this->redirect('/vendors');
    }

    /** Active ⇄ inactive. */
    public function toggle(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        VendorRepository::toggle((int) $row['id']);
        $nowActive = (int) $row['is_active'] !== 1;
        Activity::log('updated', 'vendor', (int) $row['id'], ($nowActive ? 'Activated' : 'Deactivated') . ' vendor ' . $row['name']);
        Flash::success($row['name'] . ($nowActive ? ' is active again.' : ' is now inactive — it will not appear in pick lists.'));
        $this->redirect($this->safeBack('/vendors/' . (int) $row['id']));
    }

    /** @return array{q:string,type:string,city:string,active:string} */
    private function filters(): array
    {
        $type = (string) $this->request->query('type', '');
        $active = (string) $this->request->query('active', '');
        return [
            'q' => mb_substr(trim((string) $this->request->query('q', '')), 0, 100),
            'type' => isset(VendorRepository::TYPES[$type]) ? $type : '',
            'city' => mb_substr(trim((string) $this->request->query('city', '')), 0, 120),
            'active' => in_array($active, ['0', '1'], true) ? $active : '',
        ];
    }

    /** Only allow redirects back to a vendors page (never an outside URL). */
    private function safeBack(string $default): string
    {
        $back = (string) $this->request->input('back', '');
        return preg_match('#^/vendors(/[0-9a-z-]*)*(\?[A-Za-z0-9=&%_.+\-]*)?$#', $back) ? $back : $default;
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = VendorRepository::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    /** @param array<string,mixed>|null $row */
    private function form(?array $row, string $type = 'hotel'): never
    {
        $this->view('admin/vendors/form', [
            'title' => $row ? 'Edit vendor' : 'Add vendor',
            'pageTitle' => $row ? 'Edit ' . $row['name'] : 'Add a hotel or vendor',
            'pretitle' => 'Hotels & vendors',
            'vendor' => $row,
            'defaultType' => $type,
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
        $in['gstin'] = strtoupper(str_replace(' ', '', $in['gstin']));

        $v = Validator::make($in, [
            'type' => 'required|in:' . implode(',', array_keys(VendorRepository::TYPES)),
            'name' => 'required|string|max:160',
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|phone|max:30',
            'email' => 'nullable|email|max:190',
            'city' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'gstin' => 'nullable|string|max:20',
            'bank_details' => 'nullable|string|max:255',
            'rating' => 'nullable|int|min:1|max:5',
            'notes' => 'nullable|string|max:2000',
        ], [
            'type' => 'Type',
            'name' => 'Name',
            'contact_person' => 'Contact person',
            'gstin' => 'GSTIN',
            'bank_details' => 'Bank / UPI details',
        ]);
        $errors = $v->errors();
        if (!isset($errors['gstin']) && $in['gstin'] !== ''
            && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $in['gstin'])) {
            $errors['gstin'] = 'GSTIN should look like 01ABCDE1234F1Z5 (15 characters). Leave it empty if the vendor has none.';
        }
        if ($errors !== []) {
            return [[], $errors];
        }
        $c = $v->validated();
        return [[
            'type' => $c['type'],
            'name' => $c['name'],
            'contact_person' => $c['contact_person'],
            'phone' => $c['phone'],
            'email' => $c['email'],
            'city' => $c['city'] !== null ? mb_convert_case((string) $c['city'], MB_CASE_TITLE) : null,
            'address' => $c['address'],
            'gstin' => $c['gstin'],
            'bank_details' => $c['bank_details'],
            'rating' => $c['rating'],
            'notes' => $c['notes'],
            'is_active' => in_array($this->request->input('is_active'), ['1', 'on', 1], true) ? 1 : 0,
        ], []];
    }
}
