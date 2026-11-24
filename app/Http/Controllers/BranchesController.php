<?php

namespace App\Http\Controllers;

use App\Models\Branches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BranchesController extends Controller
{
    /** DB paths are stored as storage/Branches/... — public disk root is storage/app/public */
    private function storagePublicRelativePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return ltrim(preg_replace('#^storage/+#', '', $path), '/');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Item = Branches::all();
        return $Item;
    }
    public function showID($id)
    {
        $Branches = Branches::find($id);
        if (!$Branches) {
            return $this->returnBadRequest('Branches not found');
        } else {
            return $this->returnSuccess('Success', $Branches);
        }
    }

    private function branchValidationRules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'business_name'   => 'required|string|max:255',
            'phone'           => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'license_number'  => 'required|string|max:255',
            'cal_com'         => 'required|in:all,only',
            'address'         => 'required|string|max:500',
            'tax_id'          => 'nullable|string|max:255',
            'province_id'     => 'nullable|integer|exists:provinces,id',
            'district_id'     => 'nullable|integer|exists:districts,id',
            'subDistrict_id'  => 'nullable|integer|exists:sub_districts,id',
        ];
    }

    private function fillBranchAttributes(Branches $item, Request $request): void
    {
        $item->name = $request->input('name');
        $item->business_name = $request->input('business_name');
        $item->phone = $request->input('phone');
        $item->email = $request->input('email');
        $item->province_id = $request->input('province_id');
        $item->district_id = $request->input('district_id');
        $item->subDistrict_id = $request->input('subDistrict_id');
        $item->license_number = $request->input('license_number');
        $item->postal_code = $request->input('postal_code');
        $item->address = $request->input('address');
        $item->tax_id = $request->input('tax_id');
        $item->open_close_time = $request->input('open_close_time');
        $item->open_date = $request->input('open_date');
        $item->cal_com = $request->input('cal_com');
        $item->approval_letter_number = $request->input('approval_letter_number');
        $item->expiration_date = $request->input('expiration_date');
        $item->book_number_ry_lpg = $request->input('book_number_ry_lpg');
        $item->book_number_ks_lpg = $request->input('book_number_ks_lpg');
        $item->book_number_ry_ngv = $request->input('book_number_ry_ngv');
        $item->book_number_ks_ngv = $request->input('book_number_ks_ngv');
        $item->expired_ry_lpg = $request->input('expired_ry_lpg');
        $item->expired_ks_lpg = $request->input('expired_ks_lpg');
        $item->expired_ry_ngv = $request->input('expired_ry_ngv');
        $item->expired_ks_ngv = $request->input('expired_ks_ngv');
    }

    public function store(Request $request)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make($request->all(), $this->branchValidationRules());
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'กรุณากรอกข้อมูลให้ครบถ้วน');
        }

        DB::beginTransaction();

        try {
            $Item = new Branches;
            $this->fillBranchAttributes($Item, $request);
            $Item->save();

            if ($request->hasFile('logo')) {
                $oldLogo = $this->storagePublicRelativePath($Item->logo);
                if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                    Storage::disk('public')->delete($oldLogo);
                }
                $name = 'logo';
                $picname = $Item->id;
                $newLogo = $this->saveImage_branchs($request, $name, $picname);
                if ($newLogo) {
                    $Item->logo = $newLogo;
                }
            }

            if ($request->hasFile('logo_under')) {
                $oldLogoUnder = $this->storagePublicRelativePath($Item->logo_under);
                if ($oldLogoUnder && Storage::disk('public')->exists($oldLogoUnder)) {
                    Storage::disk('public')->delete($oldLogoUnder);
                }
                $filename = 'logo_under';
                $picname = $Item->id;
                $newLogoUnder = $this->saveImageunder_branchs($request, $filename, $picname);
                if ($newLogoUnder) {
                    $Item->logo_under = $newLogoUnder;
                }
            }

            $Item->save();

            DB::commit();

            // บันทึก log
            $userId = $loginBy->id;
            $type = 'เพิ่มรายการ';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' สาขา ' . ($Item->name ?? '');
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $Item->fresh());
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->returnErrorData('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง ' . $e->getMessage(), 404);
        }
    }



    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $validator = Validator::make($request->all(), $this->branchValidationRules());
        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'กรุณากรอกข้อมูลให้ครบถ้วน');
        }

        DB::beginTransaction();

        try {
            $item = Branches::where('id', $id)->first();
            if (!$item) {
                return $this->returnErrorData('ไม่พบข้อมูลสาขา', 404);
            }
            $this->fillBranchAttributes($item, $request);
            $item->save();

            if ($request->hasFile('logo')) {
                $oldLogo = $this->storagePublicRelativePath($item->logo);
                if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                    Storage::disk('public')->delete($oldLogo);
                }
                $filename = 'logo';
                $picname = $item->id;
                $newLogo = $this->saveImage_branchs($request, $filename, $picname);
                if ($newLogo) {
                    $item->logo = $newLogo;
                }
            }

            if ($request->hasFile('logo_under')) {
                $oldLogoUnder = $this->storagePublicRelativePath($item->logo_under);
                if ($oldLogoUnder && Storage::disk('public')->exists($oldLogoUnder)) {
                    Storage::disk('public')->delete($oldLogoUnder);
                }
                $filename = 'logo_under';
                $picname = $item->id;
                $newLogoUnder = $this->saveImageunder_branchs($request, $filename, $picname);
                if ($newLogoUnder) {
                    $item->logo_under = $newLogoUnder;
                }
            }

            $item->save();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item->fresh());
        } catch (\Throwable $e) {
            DB::rollback();

            return $this->returnErrorData('system error please try again ' . $e, 404);
        }
    }

    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {

            $Item = Branches::find($id);
            if (!$Item) {
                return $this->returnErrorData('ไม่พบข้อมูลที่ต้องการลบ',404);
            }
            $branchName = $Item->name;
            $branchId = $Item->id;
            $Item->delete();

            DB::commit();

            //log
            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' สาขา id ' . $branchId . ' (' . $branchName . ')';
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $Item);
        } catch (\Throwable $e) {

            DB::rollback();

            return $this->returnErrorData('system error' . $e, 404);
        }
    }
    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;


        $columns = [
            'id',
            'name',
            'business_name',
            'phone',
            'logo',
            'email',
            'license_number',
            'cal_com',
            'address',
            'postal_code',
            'tax_id',
            'open_close_time',
            'open_date',
            'cal_com',
            'approval_letter_number',
            'expiration_date',
            'logo_under'
        ];

        $searchcolumn = [
            'name',
            'business_name',
            'phone',
            'logo',
            'email'
        ];
        $query = Branches::select($columns);


        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);


        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search,$searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }


        $items = $query->paginate($length, ['*'], 'page', $page);


        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
