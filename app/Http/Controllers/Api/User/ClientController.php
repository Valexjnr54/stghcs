<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Gig;
use App\Models\GigType;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:DSW|CSP']);
    }
    
    public function show(Request $request)
    {
        $client = Client::where(['id'=> $request->client_id])->first();
        if (!$client) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Client does not exist'], 404);
        }
        $gig = Gig::where(['id' => $request->gig_id])->first();
        $gig_type = GigType::find($gig->gig_type_id);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Client waiver activities successfully fetched', 'data'=>$gig_type], 200);
    }
}
