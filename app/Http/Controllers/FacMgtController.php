<?php namespace App\Http\Controllers;

use App\Classes\EmailHelper;
use App\Classes\Helper;
use App\Classes\SMFHelper;
use Auth;
use App\Models\Transfers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Facility;
use App\Classes\RoleHelper;
use App\Models\Actions;
use Illuminate\Support\Facades\Cache;

class FacMgtController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        //       $this->middleware('ins');
    }


    public function getIndex($fac = null) {
        if (!RoleHelper::isMentor() && !RoleHelper::isInstructor() && !RoleHelper::isFacilitySeniorStaff() &&
            !RoleHelper::isVATUSAStaff() && !RoleHelper::isWebTeam() && !RoleHelper::hasRole(Auth::user()->cid,
                $fac ?? Auth::user()->facility, "WM")) {
            abort(401);
        }

        if ($fac === null) {
            if (\Auth::user()->facility == "ZHQ") {
                $fac = "HCF";
            } else {
                $fac = \Auth::user()->facility;
            }
        }

        if ($fac == "Winterfell") {
            return view('eastereggs.winterfell');
        }

        // Mentor-only users can only view their facility
        if (RoleHelper::isMentor() && !(RoleHelper::isFacilityStaff() || RoleHelper::isInstructor())) {
            $fac = Auth::user()->facility;
        }

        $facility = Facility::find($fac);

        $promotionEligible = Cache::get("promotionEligible-$fac") ?? "N/A";

        return view('mgt.facility.index',
            ['fac' => $fac, 'facility' => $facility, 'promotionEligible' => $promotionEligible]);
    }

    public function postAPIGenerate(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(401);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, $facility,
                "WM") && !RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(500);
        }

        $key = base64_encode(random_bytes(ceil(0.75 * 16)));
        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->apikey = $key;
        $facility->save();

        echo $key;

        return;
    }

    public function postAPISandboxGenerate(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(401);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, $facility,
                "WM") && !RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(500);
        }

        $key = base64_encode(random_bytes(ceil(0.75 * 16)));
        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->api_sandbox_key = $key;
        $facility->save();

        echo $key;

        return;
    }

    public function postAPIIP(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(401);
        }

        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->ip = $request->get("apiip");
        $facility->save();

        echo 1;

        return;
    }

    public function postAPISandboxIP(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(401);
        }

        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->api_sandbox_ip = $request->get("apiip");
        $facility->save();

        echo 1;

        return;
    }

    public function postULSGenerate(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, $facility,
                "WM") && !RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(500);
        }

        $key = base64_encode(random_bytes(ceil(0.75 * 16)));
        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->uls_secret = $key;
        $facility->save();

        echo $key;

        return;
    }

    public function postULSReturn(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility,
                "WM") && !RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(500);
        }

        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->uls_return = $request->input("ret");
        $facility->save();

        return;
    }

    public function postULSDevReturn(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility,
                "WM") && !RoleHelper::isFacilitySeniorStaff(Auth::user()->cid, $facility)) {
            abort(500);
        }

        $facility = Facility::find($facility);
        if ($facility->active != 1) {
            abort(500);
        }

        $facility->uls_devreturn = $request->input("ret");
        $facility->save();

        return;
    }

    public function ajaxPosition(Request $request, $facility, $id) {
        if (!$request->ajax()) {
            abort(403);
        }

        if (!RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility, "ATM")
            && !RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility, "DATM")
            && !RoleHelper::isVATUSAStaff()) {
            abort(401);
        }

        switch ($id) {
            case 1:
                $pos = 'ATM';
                break;
            case 2:
                $pos = 'DATM';
                break;
            case 3:
                $pos = 'TA';
                break;
            case 4:
                $pos = 'EC';
                break;
            case 5:
                $pos = 'FE';
                break;
            case 6:
                $pos = 'WM';
                break;
            default:
                return "Invalid position ID";
        }
        $cid = $request->input('cid');
        $xfer = $request->input('xfer');

        $error = RoleHelper::addFacilityStaffPosition($facility, $cid, $pos, $xfer == "true");
        if ($error) {
            return $error;
        }

        $u = User::where('cid', $cid)->first();
        $un = $u->fname . ' ' . $u->lname;

        return $pos . ' successfully changed to ' . $un . '.';
    }

    public function deleteController(Request $request, $facility, $cid) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, $facility, "ATM") && !RoleHelper::hasRole(Auth::user()->cid,
                $facility, "DATM") && !RoleHelper::isVATUSAStaff()) {
            abort(401);
        }

        $user = User::find($cid);
        if (!$user) {
            abort(404);
        }

        parse_str(file_get_contents("php://input"), $vars);

        $user->removeFromFacility(Auth::user()->cid, $vars['reason']);
    }

    public function ajaxPositionDel(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(403);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, $facility, "ATM")
            && !RoleHelper::hasRole(Auth::user()->cid, $facility, "DATM")
            && !RoleHelper::isVATUSAStaff()) {
            abort(401);
        }

        if (isset($_POST['pos'])) {
            $id = intval($_POST['pos']);
            switch ($id) {
                case 1:
                    $pos = 'ATM';
                    break;
                case 2:
                    $pos = 'DATM';
                    break;
                case 3:
                    $pos = 'TA';
                    break;
                case 4:
                    $pos = 'EC';
                    break;
                case 5:
                    $pos = 'FE';
                    break;
                case 6:
                    $pos = 'WM';
                    break;
            }
            RoleHelper::deleteFacilityStaffPosition($facility, $pos);
            return 1;
        }

        return 0;
    }

    public function ajaxTransfers(Request $request, $status) {
        if (!$request->ajax()) {
            abort(500);
        }
        if (!RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility,
                "ATM") && !RoleHelper::hasRole(Auth::user()->cid, Auth::user()->facility,
                "DATM") && !RoleHelper::isVATUSAStaff()) {
            abort(401);
        }

        if (($status == 1 || $status == 2) && isset($_POST['id'])) {
            if ($status == 2 && isset($_POST['reason']) && !empty($_POST['reason'])) {
                $tr = Transfers::find($_POST['id']);
                if ($tr != null) {
                    $tr->reject(Auth::user()->cid, $_POST['reason']);

                    return 1;
                } else {
                    return 0;
                }

            }
            if ($status == 1) {
                $tr = Transfers::find($_POST['id']);
                if ($tr != null) {
                    $tr->accept(Auth::user()->cid);

                    return 1;
                } else {
                    return 0;
                }

            }
        }
    }

    public function ajaxTransferReason(Request $request) {
        if (!$request->ajax()) {
            abort(500);
        }
        //if (!RoleHelper::hasRole(\Auth::user()->cid, \Auth::user()->facility, "ATM") && !RoleHelper::hasRole(\Auth::user()->cid, \Auth::user()->facility, "DATM") && !RoleHelper::isVATUSAStaff()) abort(401);

        if (isset($_REQUEST['id'])) {
            $t = Transfers::where('id', $_REQUEST['id'])->count();
            if ($t) {
                $t = Transfers::where('id', $_REQUEST['id'])->first();

                return $t->reason;
            }
        }
    }

    public function ajaxStaffTable(Request $request, $facility) {
        if (!$request->ajax()) {
            abort(500);
        }

        $fac = $facility;
        $facility = Facility::where('id', $fac)->first();
        $id = $facility->id;

        return View('mgt.facility.partial_stafftable', [
            'atm' => RoleHelper::getNameFromRole('ATM', $id, 1),
            'datm' => RoleHelper::getNameFromRole('DATM', $id, 1),
            'ta' => RoleHelper::getNameFromRole('TA', $id, 1),
            'ec' => RoleHelper::getNameFromRole('EC', $id, 1),
            'fe' => RoleHelper::getNameFromRole('FE', $id, 1),
            'wm' => RoleHelper::getNameFromRole('WM', $id, 1),
            'fac' => $facility->id
        ]);
    }
}
