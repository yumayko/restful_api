<?php

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['api'])->group(function () {

    //register new user
    Route::post('/register', function (Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'age' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'age' => $request->age,
            'membership_status' => $request->membership_status,
        ]);

        return response()->json(['message' => 'User created successfully'], 201);
    });

    //Login User
    Route::post('/login', function (Request $request){
        $credentials = $request->only('email', 'password');

        try{
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid Email or Password'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token', 500]);
        }
        return response()->json(['token' => $token], 200);
    });

    //protected routes
    Route::middleware(['jwt.auth', 'throttle:60,1'])->group(function (){

        //get all users with pagination and caching
        Route::get('/users', function (){
            $users = Cache::remember('users', 60, function (){
                return User::select('id', 'name', 'email', 'age', 'membership_status')->paginate(10);
            });
            return response()->json($users, 200);
        });

        //get user by id
        Route::get('/users/{id}', function ($id){
            $user = Cache::remember("user_{$id}", 60, function () use ($id){
                return User::select('id', 'name', 'email', 'age', 'membership_status')->find($id);
            });

            if (!$user) {
                return response()->json(['error' => 'User not Found'], 404);
            }
            return response()->json($user, 200);
        });

        //create new user
        Route::post('/users', function (Request $request) {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'age' => 'required|integer|min:0',
            ]);

            if($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'age' => $request->age,
                'membership_status' => $request->membership_status,
            ]);

            return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
        });

        //Update user by id
        Route::put('/users/{id}', function (Request $request, $id){
            $user = User::find($id);

            if (!$user) {
                return response()->json(['error' => 'User not Found'], 404);
            }

            $validator = Validator::make($request->all(),[
                'name' => 'string|max:255',
                'email' => 'string|email|max:255|unique:users,email,'. $id,
                'password' => 'string|min:6',
                'membership_status' => 'string|nullable',
            ]);

            if($validator->fails()){
                return response()->json($validator->errors(), 400);
            }

            $user->update($request->only([
                'name' => $request->input('name', $user->name),
                'email' => $request->input('email', $user->email),
                'age' => $request->input('age', $user->age),
                'membership_status' => $request->input('membership_status', $user->membership_status),
            ]));

            //clear cache for update user and user list

            Cache::forget("user_{$id}");
            Cache::forget('users');

            return response()->json(['message' => 'User updated successfully'], 200);
        });

        //Delete user by id
        Route::delete('/users/{id}', function ($id){
            $user = User::find($id);

            if (!$user){
                return response()->json(['error' => 'User not found'], 404);
            }

            $user->delete();

            //clear cache for delete user and user list
            Cache::forget("user_{$id}");
            Cache::forget('users');

            return response()->json(['message' => 'User deleted successfully'], 200);
        });
    });
});

//add error handling for 404
Route::fallback(function (){
    return response()->json(['error' => 'Route not found'], 404);
});