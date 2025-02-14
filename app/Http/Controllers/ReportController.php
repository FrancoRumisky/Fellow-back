<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\GlobalFunction;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostContent;
use App\Models\Report;
use App\Models\UserNotification;
use App\Models\Users;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class ReportController extends Controller
{

    function addUserReport(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'reason' => 'required',
            'description' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return json_encode([
                'status' => false,
                'message' => 'user not found!',
            ]);
        }

        if ($user->is_block == 0) {

            $reportType = 1;

            $report = new Report();
            $report->user_id = $request->user_id;
            $report->type = $reportType;
            $report->reason = $request->reason;
            $report->description = $request->description;
            $report->save();
            return response()->json([
                'status' => true,
                'message' => __('app.AddSuccessful'),
                'data' => $report,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'user already blocked!',
            ]);
        }
    }

    function fetchUsersReport(Request $request)
    {

        $reportType = 1;
        $totalData = Report::where('type', $reportType)->count();
        $rows = Report::where('type', $reportType)->orderBy('id', 'DESC')->with('user')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'type'
        );
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Report::where('type', $reportType)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)->with('user')
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Report::where('type', $reportType)->with('user')
                ->whereHas('user', function ($query) use ($search) {
                    $query->Where('fullname', 'LIKE', "%{$search}%")
                        ->orWhere('identity', 'LIKE', "%{$search}%");
                })
                ->orWhere('reason', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)->with('user')
                ->get();
            $totalFiltered = Report::where('type', $reportType)->with('user')
                ->whereHas('user', function ($query) use ($search) {
                    $query->Where('fullname', 'LIKE', "%{$search}%")
                        ->orWhere('identity', 'LIKE', "%{$search}%");
                })
                ->orWhere('reason', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $imgUrl = "http://placehold.jp/150x150.png"; // Default placeholder image URL

            if ($item->user->images->isNotEmpty() && $item->user->images[0]->image != null) {
                $imgUrl = asset('storage/' . $item->user->images[0]->image);
            }

            $image = '<img src="' . $imgUrl . '" width="50" height="50">';

            $reason = '<span class="item-description"> ' . $item->reason . ' </span>';
            $description = '<span class="item-description"> ' . $item->description . ' </span>';

            $block = '<a class="btn btn-danger text-white block" rel=' . $item->user->id . ' >' . __('app.Block') . '</a>';

            $rejectReport = '<a href="#" class="me-3 btn btn-orange px-4 text-white rejectReport d-flex align-items-center" rel=' . $item->id . ' data-tooltip="Reject Report" >' . __(' <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> <span class="ms-2"> Reject </span>') . '</a>';
            $action = '<span class="float-end d-flex">' . $rejectReport . $block . ' </span>';

            $data[] = array(
                $image,
                $item->user->identity,
                $item->user->fullname,
                $reason,
                $description,
                $action
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    public function rejectUserReport(Request $request)
    {
        $report = Report::where('id', $request->report_id)->first();

        if ($report) {
            $userReports = Report::where('user_id', $report->user_id)->get();
            $userReports->each->delete();

            return response()->json([
                'status' => true,
                'message' => 'Reject User Report Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Report Not Found',
            ]);
        }
    }

    public function postReportList(Request $request)
    {
        $reportType = 0;
        $totalData = Report::where('type', $reportType)->count();
        $rows = Report::where('type', $reportType)->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = [
            0 => 'id',
            1 => 'post_id',
            2 => 'reason',
            3 => 'description',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;

        if (empty($request->input('search.value'))) {
            $result = Report::where('type', $reportType)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = Report::where('type', $reportType)
                ->Where('reason', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Report::where('type', $reportType)
                ->Where('reason', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = [];
        foreach ($result as $item) {

            $post = Post::where('id', $item->post_id)->first();

            if ($post->description == null) {
                $post->description = 'Note: Post has no description';
            }
            $postContents = $post->content;
            $firstContent = $post->content->first();
            $postContentList = $post->content->pluck('content');

            if ($postContents == null) {
                $postContents = 'Note: Post is Empty';
            }

            if ($firstContent == null) {
                $viewPost =
                    '<button type="button" class="btn btn-primary viewDescPost commonViewBtn" data-bs-toggle="modal" data-description="' .
                    $post->description .
                    '" rel="' .
                    $item->id .
                    '">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-type"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="4" x2="12" y2="20"></line></svg> View Post</button>';
            } elseif ($firstContent->content_type == 1) {
                $viewPost =
                    '<button type="button" class="btn btn-primary viewVideoPost commonViewBtn" data-bs-toggle="modal" data-image=' .
                    $firstContent .
                    ' data-description="' .
                    $post->description .
                    '" rel="' .
                    $item->id .
                    '">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-video"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> View Post</button>';
            } else {
                $viewPost =
                    '<button type="button" class="btn btn-primary viewPost commonViewBtn" data-bs-toggle="modal" data-image=' .
                    $postContentList .
                    ' data-description="' .
                    $post->description .
                    '" rel="' .
                    $item->id .
                    '">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-image"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> View Post</button>';
            }

            $description = '<span class="item-description"> ' . $post->description . ' </span>';

            $rejectReport = '<a href="#" class="me-3 btn btn-orange px-4 text-white rejectReport d-flex align-items-center" rel=' . $item->id . ' data-tooltip="Reject Report" >' . __('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> <span class="ms-2"> Reject </span>') . '</a>';
            $delete = '<a href="#" class="btn btn-danger px-4 text-white delete deletePost d-flex align-items-center" rel=' . $item->id . ' data-tooltip="Delete Post">' . __('Delete ') . '</a>';
            $action = '<span class="float-right d-flex">' . $rejectReport . $delete . ' </span>';

            $data[] = [

                $viewPost,
                $item->reason,
                $description,
                $action
            ];
        }
        $json_data = [
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];
        echo json_encode($json_data);
        exit();
    }

    public function eventReportList(Request $request)
    {
        $reportType = 2; // Tipo de reporte para eventos
        $totalData = Report::where('type', $reportType)->count();
        $rows = Report::where('type', $reportType)->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = [
            0 => 'id',
            1 => 'event_id',
            2 => 'reason',
            3 => 'description',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;

        if (empty($request->input('search.value'))) {
            $result = Report::where('type', $reportType)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = Report::where('type', $reportType)
                ->where('reason', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Report::where('type', $reportType)
                ->where('reason', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->count();
        }

        $data = [];
        foreach ($result as $item) {
            $event = Event::where('id', $item->event_id)->first();

            if (!$event) {
                continue; // Si el evento no existe, pasamos al siguiente
            }

            // Construcción de la URL correcta para la imagen
            $eventImage = $event->image ? asset('public/storage/' . $event->image) : asset('default-image.jpg');

            $viewEvent = '<button type="button" class="btn btn-primary viewEvent commonViewBtn" 
                        data-bs-toggle="modal" 
                        data-title="' . htmlspecialchars($event->title, ENT_QUOTES) . '" 
                        data-description="' . htmlspecialchars($event->description, ENT_QUOTES) . '" 
                        data-image="' . $eventImage . '" 
                        rel="' . $item->id . '">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle></svg> View Event
                     </button>';

            $description = '<span class="item-description">' . htmlspecialchars($event->description, ENT_QUOTES) . '</span>';

            $rejectReport = '<a href="#" class="me-3 btn btn-orange px-4 text-white rejectReport d-flex align-items-center" rel=' . $item->id . ' data-tooltip="Reject Report">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x-circle">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line></svg> <span class="ms-2"> Reject </span>
                     </a>';

            $deleteEvent = '<a href="#" class="btn btn-danger px-4 text-white deleteEvent d-flex align-items-center" rel=' . $item->event_id . ' data-tooltip="Delete Event">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6L18.72 20.58A2 2 0 0 1 16.72 22H7.28A2 2 0 0 1 5.28 20.58L5 6m5 4v6m4-6v6"></path></svg> Delete
                     </a>';

            $action = '<span class="float-right d-flex">' . $rejectReport . $deleteEvent . ' </span>';

            $data[] = [
                $viewEvent,
                $item->reason,
                $description,
                $action
            ];
        }

        $json_data = [
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];
        echo json_encode($json_data);
        exit();
    }

    public function deleteReport(Request $request)
    {
        $report = Report::where('id', $request->report_id)->first();

        if ($report) {
            $postReports = Report::where('post_id', $report->post_id)->get();
            $postReports->each->delete();

            return response()->json([
                'status' => true,
                'message' => 'Report Delete Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Report Not Found',
            ]);
        }
    }


    public function deletePostFromReport(Request $request)
    {

        $report = Report::where('id', $request->report_id)->first();

        if ($report) {
            $postContents = PostContent::where('post_id', $report->post_id)->get();
            foreach ($postContents as $postContent) {
                GlobalFunction::deleteFile($postContent->content);
                GlobalFunction::deleteFile($postContent->thumbnail);
            }
            $postContents->each->delete();

            $postComments = Comment::where('post_id', $report->post_id)->get();
            $postComments->each->delete();

            $postLikes = Like::where('post_id', $report->post_id)->get();
            $postLikes->each->delete();

            $deleteReportRecords = Report::where('post_id', $report->post_id)->get();
            $deleteReportRecords->each->delete();

            $userNotification = UserNotification::where('post_id', $report->post_id)->get();
            $userNotification->each->delete();

            $post = Post::where('id', $report->post_id)->first();
            $post->delete();

            $report->delete();

            return response()->json([
                'status' => true,
                'message' => 'Post Delete Successfully',
                'data' => $postContents,
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'Report Not Found',
        ]);
    }

    public function rejectEventReport(Request $request)
    {
        // Buscar el reporte de evento
        $report = Report::where('id', $request->report_id)->first();

        if ($report) {
            // Eliminar el reporte del evento
            $eventReports = Report::where('event_id', $report->event_id)->get();
            $eventReports->each->delete();

            return response()->json([
                'status' => true,
                'message' => 'Event report rejected successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Report not found',
            ]);
        }
    }

    public function deleteEventFromReport(Request $request)
    {
        // Buscar el reporte de evento
        $report = Report::where('id', $request->report_id)->first();

        if ($report) {
            // Buscar el evento
            $event = Event::where('id', $report->event_id)->first();

            if ($event) {
                // Eliminar reportes relacionados con el evento
                Report::where('event_id', $event->id)->delete();

                // Eliminar notificaciones relacionadas con el evento
                UserNotification::where('event_id', $event->id)->delete();

                // Finalmente, eliminar el evento (esto elimina asistentes automáticamente)
                $event->delete();

                return response()->json([
                    'status' => true,
                    'message' => 'Event deleted successfully',
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Event not found',
                ]);
            }
        }
        return response()->json([
            'status' => false,
            'message' => 'Report not found',
        ]);
    }
}
