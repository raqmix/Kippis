<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SupportTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @group Support Tickets
 *
 * APIs for managing support tickets.
 *
 * Support tickets allow customers to submit inquiries, report issues, or request assistance. Authenticated customers can create and view their own tickets, while unauthenticated users can create tickets by providing their contact information.
 *
 * <aside class="notice">
 * When authenticated, customers automatically see only their own tickets. Unauthenticated users must provide an email to view tickets.
 * </aside>
 */
class SupportTicketController extends Controller
{
    /**
     * Create a new support ticket.
     *
     * Create a new support ticket. If the customer is authenticated, the ticket will be automatically linked to their account and their name/email will be used. Unauthenticated users must provide name and email.
     *
     * <aside class="notice">
     * If you are authenticated, the `name` and `email` fields are optional and will be automatically filled from your account. If you are not authenticated, both fields are required.
     * </aside>
     *
     * @bodyParam subject string required The ticket subject. Maximum 255 characters. Example: Order Issue - Missing Items
     * @bodyParam message string required The ticket message describing the issue or inquiry. Example: I received my order but it's missing 2 items from my order #12345
     * @bodyParam name string optional Customer name (required if not authenticated). Maximum 255 characters. Example: John Doe
     * @bodyParam email string optional Customer email (required if not authenticated). Must be a valid email address. Example: john@example.com
     * @bodyParam priority string optional Ticket priority level. Options: `low`, `medium`, `high`. Default: `medium`. Example: high
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Support ticket created successfully.",
     *   "data": {
     *     "id": 1,
     *     "ticket_number": "TKT-67890ABCDEF",
     *     "subject": "Order Issue - Missing Items",
     *     "message": "I received my order but it's missing 2 items from my order #12345",
     *     "status": "open",
     *     "priority": "high",
     *     "created_at": "2024-01-15T10:30:00.000000Z",
     *     "updated_at": "2024-01-15T10:30:00.000000Z",
     *     "replies": []
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "error": {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *       "subject": ["The subject field is required."],
     *       "message": ["The message field is required."]
     *     }
     *   }
     * }
     *
     * @responseField success boolean Indicates if the request was successful.
     * @responseField message string Success message.
     * @responseField data object The created support ticket.
     * @responseField data.id int The ticket ID.
     * @responseField data.ticket_number string Unique ticket number (format: TKT-XXXXXXXX).
     * @responseField data.subject string The ticket subject.
     * @responseField data.message string The ticket message.
     * @responseField data.status string Ticket status. Options: `open`, `in_progress`, `closed`.
     * @responseField data.priority string Ticket priority. Options: `low`, `medium`, `high`.
     * @responseField data.created_at string Ticket creation timestamp (ISO 8601 format).
     * @responseField data.updated_at string Ticket last update timestamp (ISO 8601 format).
     * @responseField data.replies array Array of ticket replies (empty for new tickets).
     */
    public function store(Request $request): JsonResponse
    {
        $customer = Auth::guard('api')->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'name' => $customer ? 'nullable|string|max:255' : 'required|string|max:255',
            'email' => $customer ? 'nullable|email|max:255' : 'required|email|max:255',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $ticket = SupportTicket::create([
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'name' => $customer ? $customer->name : $validated['name'],
            'email' => $customer ? $customer->email : $validated['email'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'open',
            'customer_id' => $customer?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => new SupportTicketResource($ticket),
        ], 201);
    }

    /**
     * Get list of support tickets.
     *
     * Retrieve a paginated list of support tickets. Authenticated customers can only see their own tickets. Unauthenticated users must provide an email parameter to view tickets associated with that email.
     *
     * <aside class="notice">
     * If you are authenticated, you will automatically see only your own tickets. If you are not authenticated, you must provide the `email` query parameter to view tickets for that email address.
     * </aside>
     *
     * @queryParam status string optional Filter tickets by status. Options: `open`, `in_progress`, `closed`. Example: open
     * @queryParam priority string optional Filter tickets by priority. Options: `low`, `medium`, `high`. Example: high
     * @queryParam email string optional Email address to filter tickets (required if not authenticated). Example: john@example.com
     * @queryParam page int optional The page number to retrieve. Defaults to 1. Example: 1
     * @queryParam per_page int optional The number of items per page. Defaults to 15. Maximum is 100. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "ticket_number": "TKT-67890ABCDEF",
     *       "subject": "Order Issue - Missing Items",
     *       "message": "I received my order but it's missing 2 items",
     *       "status": "open",
     *       "priority": "high",
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z",
     *       "replies": []
     *     },
     *     {
     *       "id": 2,
     *       "ticket_number": "TKT-12345FEDCBA",
     *       "subject": "Payment Refund Request",
     *       "message": "I would like to request a refund for order #12345",
     *       "status": "in_progress",
     *       "priority": "medium",
     *       "created_at": "2024-01-14T14:20:00.000000Z",
     *       "updated_at": "2024-01-14T15:45:00.000000Z",
     *       "replies": []
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 2,
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 25
     *   },
     *   "links": {
     *     "first": "http://localhost/api/v1/support-tickets?page=1",
     *     "last": "http://localhost/api/v1/support-tickets?page=2",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/support-tickets?page=2"
     *   }
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "error": {
     *     "code": "AUTHENTICATION_REQUIRED",
     *     "message": "You must be authenticated or provide an email to view tickets."
     *   }
     * }
     *
     * @responseField success boolean Indicates if the request was successful.
     * @responseField data array An array of support ticket resources.
     * @responseField data.*.id int The ticket ID.
     * @responseField data.*.ticket_number string Unique ticket number.
     * @responseField data.*.subject string The ticket subject.
     * @responseField data.*.message string The ticket message.
     * @responseField data.*.status string Ticket status. Options: `open`, `in_progress`, `closed`.
     * @responseField data.*.priority string Ticket priority. Options: `low`, `medium`, `high`.
     * @responseField data.*.created_at string Ticket creation timestamp (ISO 8601 format).
     * @responseField data.*.updated_at string Ticket last update timestamp (ISO 8601 format).
     * @responseField data.*.replies array Array of ticket replies.
     * @responseField meta object Pagination metadata.
     * @responseField meta.current_page int The current page number.
     * @responseField meta.from int The starting record number of the current page.
     * @responseField meta.last_page int The last page number.
     * @responseField meta.per_page int The number of records per page.
     * @responseField meta.to int The ending record number of the current page.
     * @responseField meta.total int The total number of records.
     * @responseField links object Pagination links.
     * @responseField links.first string URL to the first page.
     * @responseField links.last string URL to the last page.
     * @responseField links.prev string|null URL to the previous page, or null if on the first page.
     * @responseField links.next string|null URL to the next page, or null if on the last page.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = Auth::guard('api')->user();

        $request->validate([
            'status' => 'nullable|in:open,in_progress,closed',
            'priority' => 'nullable|in:low,medium,high',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SupportTicket::query();

        // If customer is authenticated, only show their tickets
        if ($customer) {
            $query->where('customer_id', $customer->id);
        } else {
            // If not authenticated, require email filter
            if (!$request->has('email')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTHENTICATION_REQUIRED',
                        'message' => 'You must be authenticated or provide an email to view tickets.',
                    ],
                ], 401);
            }

            $request->validate([
                'email' => 'required|email',
            ]);

            $query->where('email', $request->input('email'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $perPage = $request->input('per_page', 15);
        $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => SupportTicketResource::collection($tickets->items()),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'from' => $tickets->firstItem(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'to' => $tickets->lastItem(),
                'total' => $tickets->total(),
            ],
            'links' => [
                'first' => $tickets->url(1),
                'last' => $tickets->url($tickets->lastPage()),
                'prev' => $tickets->previousPageUrl(),
                'next' => $tickets->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get a single support ticket.
     *
     * Retrieve detailed information about a specific support ticket, including all replies. Authenticated customers can only view their own tickets.
     *
     * <aside class="notice">
     * If you are authenticated, you can only view tickets that belong to your account. If you are not authenticated, you cannot view individual tickets (use the list endpoint with email filter instead).
     * </aside>
     *
     * @urlParam id int required The ticket ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "ticket_number": "TKT-67890ABCDEF",
     *     "subject": "Order Issue - Missing Items",
     *     "message": "I received my order but it's missing 2 items from my order #12345",
     *     "status": "open",
     *     "priority": "high",
     *     "created_at": "2024-01-15T10:30:00.000000Z",
     *     "updated_at": "2024-01-15T10:30:00.000000Z",
     *     "replies": [
     *       {
     *         "id": 1,
     *         "message": "Thank you for contacting us. We are looking into this issue.",
     *         "is_internal": false,
     *         "created_at": "2024-01-15T11:00:00.000000Z",
     *         "admin": {
     *           "id": 1,
     *           "name": "Support Team"
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "TICKET_NOT_FOUND",
     *     "message": "Support ticket not found."
     *   }
     * }
     *
     * @responseField success boolean Indicates if the request was successful.
     * @responseField data object The support ticket resource.
     * @responseField data.id int The ticket ID.
     * @responseField data.ticket_number string Unique ticket number.
     * @responseField data.subject string The ticket subject.
     * @responseField data.message string The ticket message.
     * @responseField data.status string Ticket status. Options: `open`, `in_progress`, `closed`.
     * @responseField data.priority string Ticket priority. Options: `low`, `medium`, `high`.
     * @responseField data.created_at string Ticket creation timestamp (ISO 8601 format).
     * @responseField data.updated_at string Ticket last update timestamp (ISO 8601 format).
     * @responseField data.replies array Array of ticket replies.
     * @responseField data.replies.*.id int The reply ID.
     * @responseField data.replies.*.message string The reply message.
     * @responseField data.replies.*.is_internal boolean Whether the reply is internal (not visible to customer).
     * @responseField data.replies.*.created_at string Reply creation timestamp (ISO 8601 format).
     * @responseField data.replies.*.admin object|null Admin who created the reply, or null if customer reply.
     * @responseField data.replies.*.admin.id int Admin ID.
     * @responseField data.replies.*.admin.name string Admin name.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Auth::guard('api')->user();

        $query = SupportTicket::with('replies');

        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        $ticket = $query->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TICKET_NOT_FOUND',
                    'message' => 'Support ticket not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new SupportTicketResource($ticket),
        ]);
    }
}

