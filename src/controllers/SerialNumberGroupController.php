<?php

namespace Abs\SerialNumberPkg;
use Abs\SerialNumberPkg\SerialNumberGroup;
use App\Config;
use App\Http\Controllers\Controller;
use App\State;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Validator;
use Yajra\Datatables\Datatables;

class SerialNumberGroupController extends Controller {

	public function __construct() {
	}

	public function getSerialNumberGroupList() {
		$serial_number_group_list = SerialNumberGroup::withTrashed()
			->select(
				'serial_number_segments.id',
				'serial_number_segments.name as name',
				'configs.name as type',
				DB::raw('IF((serial_number_segments.deleted_at) IS NULL,"Active","In Active") as status')
			)
			->join('configs', 'configs.id', 'serial_number_segments.data_type_id')
			->where('serial_number_segments.company_id', Auth::user()->company_id)
			->orderby('serial_number_segments.id', 'desc');

		return Datatables::of($serial_number_group_list)
			->addColumn('name', function ($serial_number_group_list) {
				$status = $serial_number_group_list->status == 'Active' ? 'green' : 'red';
				return '<span class="status-indicator ' . $status . '"></span>' . $serial_number_group_list->name;
			})
			->addColumn('action', function ($serial_number_group_list) {
				$edit_img = asset('public/theme/img/table/cndn/edit.svg');
				$delete_img = asset('public/theme/img/table/cndn/delete.svg');
				return '
					<a href="#!/serial-number-pkg/serial-number-group/edit/' . $serial_number_group_list->id . '">
						<img src="' . $edit_img . '" alt="View" class="img-responsive">
					</a>
					<a href="javascript:;" data-toggle="modal" data-target="#delete_serial_number_group"
					onclick="angular.element(this).scope().deleteSerialNumberType(' . $serial_number_group_list->id . ')" dusk = "delete-btn" title="Delete">
					<img src="' . $delete_img . '" alt="delete" class="img-responsive">
					</a>
					';
			})
			->make(true);
	}

	public function getSerialNumberGroupForm($id = NULL) {
		if (!$id) {
			$serial_number_group = new SerialNumberGroup;
			$action = 'Add';
		} else {
			$serial_number_group = SerialNumberGroup::withTrashed()
				->where('id', $id)->get();
			$action = 'Edit';
		}
		$this->data['type_list'] = Config::getSegmentTypeList();
		$this->data['state_list'] = State::getStateList();
		$this->data['serial_number_group'] = $serial_number_group;
		$this->data['action'] = $action;

		return response()->json($this->data);
	}

	public function saveSerialNumber(Request $request) {
		// dd($request->all());
		try {
			if (!empty($request->segment)) {
				foreach ($request->segment as $segments) {
					$error_messages = [
						'name.required' => 'Serial Number Segment Name is Required',
						'name.unique' => 'Serial Number Segment Name:' . $segments['name'] . ' is already taken',
					];
					$validator = Validator::make($segments, [
						'name' => [
							'required',
							'unique:serial_number_segments,name,' . $segments['id'] . ',id,company_id,' . Auth::user()->company_id,
						],
					], $error_messages);
					if ($validator->fails()) {
						return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
					}
				}
			}

			DB::beginTransaction();
			if (!empty($request->segment_removal_id)) {
				$segment_removal_id = json_decode($request->segment_removal_id, true);
				SerialNumberSegment::whereIn('id', $segment_removal_id)->delete();
			}
			foreach ($request->segment as $segments) {
				// dd($segments['id']);
				if (!$segments['id']) {
					$serial_number_segment = new SerialNumberSegment;
					$serial_number_segment->created_by_id = Auth::user()->id;
					$serial_number_segment->created_at = Carbon::now();
					$serial_number_segment->updated_at = NULL;
				} else {
					$serial_number_segment = SerialNumberSegment::withTrashed()->find($segments['id']);
					$serial_number_segment->updated_by_id = Auth::user()->id;
					$serial_number_segment->updated_at = Carbon::now();
				}
				$serial_number_segment->name = $segments['name'];
				$serial_number_segment->company_id = Auth::user()->company_id;
				$serial_number_segment->data_type_id = $segments['data_type_id'];
				if ($segments['status'] == 'Inactive') {
					$serial_number_segment->deleted_at = Carbon::now();
					$serial_number_segment->deleted_by_id = Auth::user()->id;
				} else {
					$serial_number_segment->deleted_by_id = NULL;
					$serial_number_segment->deleted_at = NULL;
				}
				$serial_number_segment->save();
			}
			DB::commit();
			foreach ($request->segment as $segments) {
				if (empty($segments['id'])) {
					return response()->json(['success' => true, 'message' => ['Serial Number Segment Added Successfully']]);
				} else {
					return response()->json(['success' => true, 'message' => ['Serial Number Segment Updated Successfully']]);
				}
			}

		} catch (Exceprion $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}
	public function deleteSerialNumberSegment($id) {
		$delete_status = SerialNumberSegment::where('id', $id)->forceDelete();
		if ($delete_status) {
			return response()->json(['success' => true]);
		}
	}
}
