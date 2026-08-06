<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\FirebaseToken;
use App\Http\Controllers\Controller;
use App\Notifications\MyFcmNotification;
use Google\Client as GoogleClient;


class FirebaseController extends Controller
{

	/**
	 * @OA\Post(
	 *     path="/api/v1/firebase/notification",
	 *     operationId="sendNotification",
	 *     tags={"Firebase"},
	 *     summary="Send FCM Notification",
	 *     description="Send a Firebase Cloud Messaging (FCM) notification to a recipient.",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="Notification data",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", description="The message to send in the notification"),
	 *             @OA\Property(property="sender_id", type="integer", description="ID of the sender"),
	 *             @OA\Property(property="recipient_id", type="integer", description="ID of the recipient"),
	 *             @OA\Property(property="post_id", type="integer", description="ID of the associated post")
     *             @OA\Property(property="type", type="string", description="Specify type if it is chat, alarm whatever...")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Notification sent successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", description="Success message: 'Notification sent successfully'")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Internal Server Error",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", description="Internal server error message")
	 *         )
	 *     )
	 * )
	 */
	public function notification($message, $sender_id, $recipient_id, $post_id, $type) {

        $projectId = 'dsconnect-f11fa';

        $credentialsFilePath = Storage::path('app/json/firebase-service-account.json');
        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();

        $access_token = $token['access_token'];

		// $url = 'https://fcm.googleapis.com/fcm/send';
        $url = 'https://fcm.googleapis.com/v1/projects/your-project-id/messages:send';
		$sender_token = FirebaseToken::where('user_id', '=', auth()->user()->id)->latest()->first();
		$recipient_token = FirebaseToken::where('user_id', '=', $recipient_id)->latest()->first();
		//echo $recipient_token->token;
		if (!$recipient_token) {
				return response()->json(['message' => "Token was not found."], 500);
		}

		$fields = [
				'registration_ids' => array ($recipient_token->token),
				'notification' => [
						"title" => "Evonit",
						"body" => $message,
						"sound" => "default",
				],
				"data" => [
						"post_id" => $post_id,
                        "type"    => $type,
				],
		];  
		
		$fields = json_encode ( $fields );
        $headers = [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json'
        ];


		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_POST, true );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );

		$result = curl_exec ( $ch );
		curl_close ( $ch );

	}

	// public function testnotification(Request $request) 
	// {

		//         // $firebaseToken = FirebaseToken::where('user_id', '=', auth()->user()->id)->first();
		//         $user = auth()->user(); // Retrieve the user to notify
		//         // $token = $firebaseToken->token;
		//         $user->notify(new MyFcmNotification);

		// }       

	/**
	 * @OA\Post(
	 *     path="/api/v1/firebase/registerToken",
	 *     operationId="registerFirebaseToken",
	 *     tags={"Firebase"},
	 *     summary="Register Firebase Token",
	 *     description="Register a Firebase Cloud Messaging (FCM) token for the authenticated user.",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="Token data",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="token", type="string", description="Firebase Cloud Messaging token to register")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Token registered successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", description="Success message: 'Token added successfully.'")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - User not authenticated",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="error", type="string", description="Unauthorized error message")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Internal Server Error",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="error", type="string", description="Internal server error message")
	 *         )
	 *     )
	 * )
	 */
    public function registerToken(Request $request)
    {

        if (auth()->guest()) {
                return response()->json(['error' => 'Unauthorized'], 401);
        } else {
                if (!$request->token) {
                        return response()->json(['error' => 'Invalid Request'], 500);
                }
                FirebaseToken::updateOrCreate(
                        ['user_id' => auth()->user()->id],
                        ['token' => $request->token]
                );      
        } 
        return response()->json(['message' => "Token added successfully."], 200);
    }

	/**
	 * @OA\Post(
	 *     path="/api/v1/firebase/unRegisterToken",
	 *     operationId="unregisterFirebaseToken",
	 *     tags={"Firebase"},
	 *     summary="Unregister Firebase Token",
	 *     description="Unregister the Firebase Cloud Messaging (FCM) token for the authenticated user.",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Token unregistered successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="message", type="string", description="Success message: 'Token unregistered successfully.'")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - User not authenticated",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="error", type="string", description="Unauthorized error message")
	 *         )
	 *     )
	 * )
	 */
    public function unRegisterToken()
    {
        if (auth()->guest()) {
                return response()->json(['error' => 'Unauthorized'], 401);
        } else {

                FirebaseToken::where('user_id', '=', auth()->user()->id)->delete();

                return response()->json(['message' => "Token unregistred successfully."], 200);
        }
		
    }
}

