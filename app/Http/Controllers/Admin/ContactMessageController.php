<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRelations\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.customer-relations.contact-messages.index');
    }

    public function show(ContactMessage $contactMessage): View
    {
        if ($contactMessage->status === 'pending') {
            $contactMessage->markAsRead();
        }

        return view('admin.customer-relations.contact-messages.show', compact('contactMessage'));
    }

    public function table(): JsonResponse
    {
        $query = ContactMessage::query()->latest();

        return datatables($query)
            ->addColumn('subject_label', function (ContactMessage $message) {
                $labels = [
                    'web-development' => 'Web Development',
                    'mobile-app' => 'Mobile App',
                    'pos-system' => 'POS System',
                    'api-development' => 'API Development',
                    'database-design' => 'Database Design',
                    'tech-support' => 'Tech Support',
                    'other' => 'Other',
                ];

                return $labels[$message->subject] ?? $message->subject;
            })
            ->addColumn('status_badge', function (ContactMessage $message) {
                $badges = [
                    'pending' => '<span class="badge badge-light-warning">Unread</span>',
                    'read' => '<span class="badge badge-light-info">Read</span>',
                    'replied' => '<span class="badge badge-light-success">Replied</span>',
                    'archived' => '<span class="badge badge-light-secondary">Archived</span>',
                ];

                return $badges[$message->status] ?? '<span class="badge badge-light">'.$message->status.'</span>';
            })
            ->addColumn('date', function (ContactMessage $message) {
                return $message->created_at->format('M d, Y h:i A');
            })
            ->addColumn('actions', function (ContactMessage $message) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                $action .= '<a href="'.route('contact-messages.show', $message->id).'"
                    class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1"
                    data-bs-toggle="tooltip" title="View">
                    <i class="fas fa-eye"></i>
                </a>';
                $action .= '<button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm"
                    value="'.$message->id.'" data-bs-toggle="tooltip" title="Delete">
                    <i class="fas fa-trash"></i></button>';
                $action .= '<input type="hidden" id="name_'.$message->id.'" value="'.e($message->name).'" />';
                $action .= '<form method="POST" action="'.route('contact-messages.destroy', $message->id).'" id="form_delete_'.$message->id.'">'.method_field('DELETE').csrf_field().'</form>';
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted successfully!',
        ]);
    }

    public function markAsReplied(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->markAsReplied();

        return response()->json([
            'success' => true,
            'message' => 'Marked as replied.',
        ]);
    }

    public function archive(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->archive();

        return response()->json([
            'success' => true,
            'message' => 'Message archived.',
        ]);
    }
}
