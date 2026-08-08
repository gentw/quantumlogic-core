<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\FirebaseController;
use App\Http\Traits\MessengerTrait;
use App\Jobs\AssignAgentToClient;
use App\Models\AgentQueue;
use App\Models\Chat;
use App\Models\ChatAgentClientOnLine;
use App\Models\Message;
use App\Models\NotificationList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;

class ChatController extends Controller
{
    use MessengerTrait;

    protected $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options', [])
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/assignAgentToClient",
     *     summary="Assigns an agent to a client in an active chat",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"client_id", "chat_id"},
     *
     *             @OA\Property(property="client_id", type="integer", example=1, description="ID of the client"),
     *             @OA\Property(property="chat_id", type="integer", example=10, description="ID of the chat session")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Agent assigned successfully with status messages",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="status", type="string", example="connected_with_me", description="The client is connected to the requesting agent")
     *                 ),
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="status", type="string", example="connected_with_another_agent", description="The client is connected to a different agent")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function assignAgentToClient(Request $request)
    {
        $clientId = $request['client_id'];
        $chatId = $request['chat_id'];

        $agentIsInLine = ChatAgentClientOnLine::where('client_id', $clientId)->where('status', 1);

        ChatAgentClientOnLine::where('agent_id', Auth::user()->id)->update([
            'live' => 0,
        ]);

        if (! $agentIsInLine->first()) {
            $chatLine = ChatAgentClientOnLine::create([
                'status' => 1,
                'client_id' => $clientId,
                'agent_id' => Auth::user()->id,
                'chat_id' => $chatId,
                'live' => 1,
            ]);

            $createdChat = Chat::where('client_id', $clientId)->update(
                [
                    'agent_id' => Auth::user()->id,
                ]
            );

            if ($chatLine) {
                return response()->json(['status' => 'connected_with_me']);
            }
        } else {
            $clientIsInLineWithMe = $agentIsInLine->where('agent_id', Auth::user()->id)->first();

            ChatAgentClientOnLine::where('agent_id', Auth::user()->id)->where('chat_id', $chatId)->update([
                'live' => 1,
            ]);

            if ($clientIsInLineWithMe) {
                return response()->json(['status' => 'connected_with_me']);
            } else {
                return response()->json(['status' => 'connected_with_another_agent']);
            }
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/checkAgentStatus",
     *     summary="Check the availability status of an agent for the client",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Agent status response",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="available", type="boolean", description="Agent availability status"),
     *                     @OA\Property(property="agent_data", type="object", nullable=true, description="Data of the available agent",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="is_busy", type="boolean", example=false),
     *                         @OA\Property(property="role", type="string", example="agent")
     *                     ),
     *                     @OA\Property(property="agent_id", type="integer", nullable=true, description="ID of the available agent")
     *                 ),
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="available", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Added to waiting queue")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function checkAgentStatus(Request $request)
    {
        $clientId = Auth::id();

        $agent = User::where('is_busy', 0)->where('role', 'agent')->first();

        $agent_on_line = ChatAgentClientOnLine::where('client_id', $clientId)
            ->where('status', 1)
            ->first();

        if ($agent) {
            $agentId = $agent->id;
            $agentData = $agent;
        }

        if ($agent_on_line) {
            $agent = true;
            $agentId = $agent_on_line->id;
            $agentData = $agent_on_line->agent;
        }

        if ($agent) {
            return response()->json([
                'available' => true,
                'agent_data' => $agentData,
                'agent_id' => $agentId,
            ]);
        } else {
            // return response()->json($clientId);
            AgentQueue::create(
                ['client_id' => $clientId]
            );

            return response()->json([
                'available' => false,
                'message' => 'Added to waiting queue',
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/sendMessage",
     *     summary="Send a message in a chat between a client and an agent",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"message"},
     *
     *             @OA\Property(property="message", type="string", example="Hello, I need assistance"),
     *             @OA\Property(property="chat_id", type="integer", example=1, description="Chat ID (required for agents)"),
     *             @OA\Property(property="client_id", type="integer", example=2, description="Client ID (required for agents)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Invalid request data")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function sendMessage(Request $request)
    {
        $fromUser = Auth::user();
        $message = $request->input('message');

        if (Auth::user()->role == 'client') {
            $client = Auth::user();
            $clientIsInLineWithAgent = ChatAgentClientOnLine::where('client_id', $client->id)->where('status', 1)->first();
            if (! $clientIsInLineWithAgent) {
                $data = [
                    'message' => $message,
                    'user_id' => $client->id,
                    'user_name' => $client->name,
                    'user_image' => $client->img,
                ];

                $createdChat = Chat::updateOrCreate(
                    [
                        'client_id' => $data['user_id'],
                        'agent_id' => null,
                    ],
                    [
                        'active' => 1,
                        'live_2' => 1,
                    ]
                );

                Message::create(
                    [
                        'chat_id' => $createdChat->id,
                        'user_id' => $createdChat->client_id,
                        'message' => $message,
                    ]
                );

                $agents = User::where('role', 'agent')->get();

                foreach ($agents as $agent) {
                    // send Message
                    $this->addGeneralNotification($client->id, $agent->id, null, $createdChat->id, 'chat', 'Nje mesazh i ri nga '.$client->name.'');

                    $chatNotif = $this->fetchChatNotification($client->id, $agent->id, $createdChat->id);

                    $data = [
                        'user_id' => $client->id,
                        'user_name' => $client->name,
                        'user_image' => $client->img,
                    ];

                    $notifData = [
                        'notification_data' => $chatNotif,
                        'user_name' => $data['user_name'],
                        'user_image' => $data['user_image'],
                    ];

                    $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);

                    // $firebaseController = App::make(FirebaseController::class);
                    // $firebaseController->notification("Nje mesazh i ri nga " . $client->name . "", $client->id, $agent->id, $createdChat->id, "chat");
                }
            } else {
                $agent = $clientIsInLineWithAgent->agent;

                if ($clientIsInLineWithAgent->live && $clientIsInLineWithAgent->chat->live_2) { //already osht tu fol
                    $data = [
                        'message' => $message,
                        'user_id' => $client->id,
                        'user_name' => $client->name,
                        'user_image' => $client->img,
                    ];

                    $this->pusher->trigger("chat.agent.{$agent->id}", 'MessageSent', $data);

                    $createdChat = Chat::updateOrCreate(
                        [
                            'client_id' => $data['user_id'],
                            'agent_id' => $agent->id,
                        ],
                        [
                            'active' => 1,
                        ]
                    );

                    Message::create(
                        [
                            'chat_id' => $createdChat->id,
                            'user_id' => $createdChat->client_id,
                            'message' => $message,
                        ]
                    );
                } else {
                    $data = [
                        'message' => $message,
                        'user_id' => $client->id,
                        'user_name' => $client->name,
                        'user_image' => $client->img,
                    ];

                    $createdChat = Chat::updateOrCreate(
                        [
                            'client_id' => $data['user_id'],
                            'agent_id' => $agent->id,
                        ],
                        [
                            'active' => 1,
                            'live_2' => 1,
                        ]
                    );

                    Message::create(
                        [
                            'chat_id' => $createdChat->id,
                            'user_id' => $createdChat->client_id,
                            'message' => $message,
                        ]
                    );

                    $updateNotif = NotificationList::where('chat_id', $clientIsInLineWithAgent->chat_id)->where('recipient_user_id', $agent->id)->update(
                        [
                            'read' => 0,
                        ]
                    );

                    if ($updateNotif) {
                        $chatNotif = $this->fetchChatNotification($client->id, $agent->id, $clientIsInLineWithAgent->chat_id);

                        $data = [
                            'message' => $message,
                            'user_id' => $client->id,
                            'user_name' => $client->name,
                            'user_image' => $client->img,
                        ];

                        $notifData = [
                            'notification_data' => $chatNotif,
                            'user_name' => $data['user_name'],
                            'user_image' => $data['user_image'],
                        ];

                        $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);
                    } else {
                        $this->addNotification($client->id, $agent->id, null, $createdChat->id, 'chat', 'Nje mesazh i ri nga '.$client->name.'');

                        $chatNotif = $this->fetchChatNotification($client->id, $agent->id, $createdChat->id);

                        $data = [
                            'user_id' => $client->id,
                            'user_name' => $client->name,
                            'user_image' => $client->img,
                        ];

                        $notifData = [
                            'notification_data' => $chatNotif,
                            'user_name' => $data['user_name'],
                            'user_image' => $data['user_image'],
                        ];

                        $this->pusher->trigger("notification.agent.{$agent->id}", 'Notification', $notifData);

                        // $firebaseController = App::make(FirebaseController::class);
                        // $firebaseController->notification("Nje mesazh i ri nga " . $client->name . "", $client->id, $agent->id, $createdChat->id, "chat");
                    }
                }

            }
        }

        if (Auth::user()->role == 'agent') {
            $chatId = $request['chat_id'];
            $clientId = $request['client_id'];
            $agent = Auth::user();
            $clientIsInLineWithAgent = ChatAgentClientOnLine::where('agent_id', $agent->id)->where('chat_id', $chatId)->where('status', 1)->first();

            if ($clientIsInLineWithAgent) {
                $clientIsInLineWithAgent->touch();
            }

            $client = $clientIsInLineWithAgent->client;

            $data = [
                'message' => $message,
                'user_id' => $agent->id,
                'user_name' => $agent->name,
                'user_image' => $agent->img,
            ];

            if ($clientIsInLineWithAgent->live && $clientIsInLineWithAgent->chat->live_2) { //already osht tu fol

                $this->pusher->trigger("chat.client.{$client->id}", 'MessageSent', $data);

                $chat = Chat::where('client_id', $client->id)->where('agent_id', $agent->id)->first();

                Message::create(
                    [
                        'chat_id' => $chat->id,
                        'user_id' => $chat->agent_id,
                        'message' => $message,
                    ]
                );
            } else {

                $chat = Chat::where('client_id', $client->id)->where('agent_id', $agent->id)->first();

                Message::create(
                    [
                        'chat_id' => $chat->id,
                        'user_id' => $chat->agent_id,
                        'message' => $message,
                    ]
                );

                $updateNotif = NotificationList::where('chat_id', $clientIsInLineWithAgent->chat_id)
                    ->where('recipient_user_id', $clientIsInLineWithAgent->client_id)
                    ->update(
                        [
                            'read' => 0,
                        ]
                    );

                if ($updateNotif) {
                    $chatNotif = $this->fetchChatNotification($agent->id, $client->id, $chat->id);

                    $notifData = [
                        'notification_data' => $chatNotif,
                        'user_name' => $data['user_name'],
                        'user_image' => $data['user_image'],
                    ];

                    $this->pusher->trigger("notification.client.{$client->id}", 'Notification', $notifData);
                } else {
                    $this->addNotification($agent->id, $client->id, null, $chat->id, 'chat', 'Nje mesazh i ri nga agjenti');

                    $chatNotif = $this->fetchChatNotification($agent->id, $client->id, $chat->id);

                    $notifData = [
                        'notification_data' => $chatNotif,
                        'user_name' => $data['user_name'],
                        'user_image' => $data['user_image'],
                    ];

                    $this->pusher->trigger("notification.client.{$client->id}", 'Notification', $notifData);

                    // $firebaseController = App::make(FirebaseController::class);
                    // $firebaseController->notification("Nje mesazh i ri nga agjenti", $agent->id, $client->id, $chat->id, "chat");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/fetchMessagesByClient",
     *     summary="Fetches messages for the authenticated client",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully or no messages found",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Array of messages",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=1, description="Message ID"),
     *                             @OA\Property(property="content", type="string", example="Hello!", description="Message content"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-27T12:45:30Z", description="Timestamp of message creation"),
     *                             @OA\Property(property="sender_id", type="integer", example=2, description="ID of the user who sent the message")
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Empty array when no messages are found",
     *
     *                         @OA\Items(type="object")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function fetchMessagesByClient()
    {
        $authUser = Auth::user()->id;

        $messages = Chat::where('client_id', $authUser)->first()->messages;

        if ($messages) {
            return response()->json(
                [
                    'messages' => $messages,
                ]
            );
        }

        return response()->json(
            [
                'messages' => [],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/fetchMessagesByChatIdAndClient",
     *     summary="Fetches messages for a given chat and client ID",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="chat_id", type="integer", example=1, description="ID of the chat session"),
     *             @OA\Property(property="client_id", type="integer", example=10, description="ID of the client")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully or no messages found",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Array of messages for the chat",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=1, description="Message ID"),
     *                             @OA\Property(property="content", type="string", example="Hello!", description="Message content"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-27T12:45:30Z", description="Timestamp of message creation"),
     *                             @OA\Property(property="sender_id", type="integer", example=2, description="ID of the user who sent the message"),
     *                             @OA\Property(property="user", type="object", description="User information",
     *                                 @OA\Property(property="id", type="integer", example=2, description="User ID"),
     *                                 @OA\Property(property="name", type="string", example="John Doe", description="User name")
     *                             )
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Schema(
     *
     *                    @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Empty array if no messages are found", example={}),
     *
     *                         @OA\Items(type="object")
     *                    )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    /**
     * @OA\Post(
     *     path="/api/v1/chat/fetchMessagesByChatIdAndClient",
     *     summary="Fetches messages for a given chat and client ID",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="chat_id", type="integer", example=1, description="ID of the chat session"),
     *             @OA\Property(property="client_id", type="integer", example=10, description="ID of the client")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully or no messages found",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Array of messages for the chat",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=1, description="Message ID"),
     *                             @OA\Property(property="content", type="string", example="Hello!", description="Message content"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-27T12:45:30Z", description="Timestamp of message creation"),
     *                             @OA\Property(property="sender_id", type="integer", example=2, description="ID of the user who sent the message"),
     *                             @OA\Property(property="user", type="object", description="User information",
     *                                 @OA\Property(property="id", type="integer", example=2, description="User ID"),
     *                                 @OA\Property(property="name", type="string", example="John Doe", description="User name")
     *                             )
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="messages",
     *                         type="array",
     *                         description="Empty array if no messages are found",
     *
     *                         @OA\Items(type="object")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function fetchMessagesByChatIdAndClient(Request $request)
    {
        $chatId = $request['chat_id'];
        $clientId = $request['client_id'];

        $messages = Chat::with('client')->where('client_id', $clientId)->where('id', $chatId)->first()->messages;

        foreach ($messages as $message) {
            $message->user;
        }

        if ($messages) {
            return response()->json(
                [
                    'messages' => $messages,
                ]
            );
        }

        return response()->json(
            [
                'messages' => [],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/chat/clientSwitchLiveOff",
     *     summary="Sets a client's live chat status to off",
     *     tags={"Chat"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Live chat status updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success", description="Indicates success in updating the status")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client or chat not found"
     *     )
     * )
     */
    public function clientSwitchLiveOff()
    {
        $clientId = Auth::user()->id;

        $chatAgentClientOnLine = ChatAgentClientOnLine::where('client_id', $clientId)->first();

        $chatAgentClientOnLine->chat->live_2 = 0;
        $chatAgentClientOnLine->chat->save();

        return response()->json([
            'status' => 'success',
        ]);
    }
}
