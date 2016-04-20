<?php

namespace App\Http\Controllers;

use App\User;
use App\Models\KorisnikDodatno;
use Illuminate\Http\Request;
use App\Models\Favorit;
use View;
use App\Http\Requests;
use ReCaptcha\ReCaptcha;

use Illuminate\Support\Facades\Mail;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;


class KorisnikController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth', ['except' => ['login', 'store', 'verifikujKorisnika']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return User::all();
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            // verify the credentials and create a token for the user
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        // if no errors are encountered we can return a JWT
        return response()->json(compact('token'));
    }


    public function verifikujKorisnika($konfirmacijski_kod)
    {

        $korisnik= User::where('konfirmacijski_kod', $konfirmacijski_kod)->first();
        $korisnik->verifikovan=1;
        $korisnik->konfirmacijski_kod=null;
        $korisnik->save();

    }

    public function captchaCheck()
    {

        $response = Input::get('g-recaptcha-response');
        $remoteip = $_SERVER['REMOTE_ADDR'];
        $secret   = "6LfQyB0TAAAAANSmMx8nrWd8DzWsVx79413MJd_v";

        $recaptcha = new ReCaptcha($secret);
        $resp = $recaptcha->verify($response, $remoteip);
        if ($resp->isSuccess()) {
            return true;
        } else {
            return false;
        }

    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try
        {


            $korisnik=new User();
            $dodatno=new KorisnikDodatno();

            $rules=array(
                'name'=>'required|max:32|regex:/^\w{2,}\s\w{2,}$/',
                'email'=>'required|email|max:255',
                'password'=>'required|min:6|max:32',
                'telefon'=>'digits_between:6,15|max:45',
                'grad'=>'alpha|max:14',
                'drzava'=>'alpha|max:14'
            );

            $validator= Validator::make(Input::all(), $rules);
            
            if($this->captchaCheck() == false)
            {
                return response()->json(['error' => 'Captcha failed'], HttpResponse::HTTP_CONFLICT);
            }
            
            if(!$validator->fails()) {
                $confirmation_code = str_random(30);
                $data = Input::except('password', 'admin');
                $korisnik->fill($data);
                $dodatno->fill($data);
                $korisnik->password=bcrypt($request->password);
                $dodatno->save();
                $korisnik->dodatno_korisnik=$dodatno->id;
                $korisnik->konfirmacijski_kod=$confirmation_code;
                $korisnik->save();
                $data=['code'=>$confirmation_code] ;
                Mail::send('emailverify', $data, function($message) use ($korisnik){
                    $message->from('postmaster@sandbox89dce16dbf084d70be50ee4548ae933b.mailgun.org', 'EasyPick');
                    $message->to( $korisnik->email, $korisnik->name)
                        ->subject('Verifikujte Vašu email adresu');
                });
               
               /* Mail::raw('http://localhost:8000/korisnici/verifikuj/'.$confirmation_code, function ($message) {

                    $message->to('zana_14t@hotmail.com');
                    $message->from('postmaster@sandbox89dce16dbf084d70be50ee4548ae933b.mailgun.org', 'EasyPick');

                    $message->subject('EayPick email verficiation ');
                }); */
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'User already exists.'], HttpResponse::HTTP_CONFLICT);
        }
        

        $token = JWTAuth::fromUser($korisnik);

        return response()->json(compact('token'));
        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return User::find($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        $rules=array(
            'name'=>'max:32|regex:/^[A-Za-zčČćĆšŠđĐžŽ]{2,}\s[A-Za-zčČćĆšŠđĐžŽ]{2,}$/',
            'email'=>'email|max:255',
           // 'password'=>'max:32',
            'telefon'=>'digits_between:6,15|max:45',
            'grad'=>'alpha|max:14',
            'drzava'=>'alpha|max:14'
        );

        $validator= Validator::make(Input::all(), $rules);

        $korisnik=User::find($id);

        if(!$validator->fails()) {

            if($user->id == $korisnik->id || $user->admin){

                $data = Input::except('email', 'password', 'admin');
                $korisnik->password=bcrypt($request->password);
                $korisnik->fill($data);

                if(isset($korisnik->dodatno_korisnik))
                    $dodatno = $korisnik->dodatno;
                else
                    $dodatno = new KorisnikDodatno();

                $dodatno->fill($data);
                $dodatno->save();
                $korisnik->dodatno_korisnik=$dodatno->id;
                $korisnik->save();
                return response()->json(['success' => 'User info updated'], HttpResponse::HTTP_OK);
            }
            else return response()->json(['error' => 'No authorization to update'], HttpResponse::HTTP_UNAUTHORIZED);

        }


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        $korisnik= User::find($id);

        if($user->id == $korisnik->id || $user->admin){
            $korisnik->delete();
            return response()->json(['success' => 'User deleted'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to delete'], HttpResponse::HTTP_UNAUTHORIZED);
    }

    public function PoEmail($email)
    {
     return User::where('email', $email)->get();
    }

    public function urediPoEmail(Request $request,  $email)
    {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        $korisnik= User::where('email', $email)->first();
        $input=$request->all();


        if($user->id == $korisnik->id || $user->admin){
            $korisnik->fill($input);
            $korisnik->save();
            return response()->json(['success' => 'User updated'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to update'], HttpResponse::HTTP_UNAUTHORIZED);


    }

    public function brisanjePoEmail ($email)
    {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        $korisnik= User::where('email', $email)->first();

        if($user->id == $korisnik->id || $user->admin){
            User::destroy($korisnik->id);
            return response()->json(['success' => 'User deleted'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to delete'], HttpResponse::HTTP_UNAUTHORIZED);

    }

    public function dajFavorite ($id)
    {
        $favoriti = array();

        $favoritikorisnika = User::find($id)->favoriti();
        foreach ($favoritikorisnika as $favorit){
            $favoriti[] = (array)$favorit;
            }
        return $favoriti;

    }

    public function dodajFavorit ($id_korisnika, $id_favorita) {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);
        if($user->id == $id_korisnika){
            $favorit=new Favorit();
            $favorit->korisnik_id=$id_korisnika;
            $favorit->oglas_id=$id_favorita;
            $favorit->save();
            return response()->json(['success' => 'Favorit added'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to add'], HttpResponse::HTTP_UNAUTHORIZED);
    }

    public function izbrisiFavorit ( $id_favorita) {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        $favorit = Favorit::find($id_favorita);
        if($user->id == $favorit->korisnik_id)
        {
            $favorit->delete();
            return response()->json(['success' => 'Favorit deleted'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to delete'], HttpResponse::HTTP_UNAUTHORIZED);
    }

    public function dodajBan ( $id) {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        if($user->admin){
            $korisnik= User::find($id);
            $korisnik->ban=1;
            $korisnik->save();
            return response()->json(['success' => 'User banned'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to ban user'], HttpResponse::HTTP_UNAUTHORIZED);
    }

    public function ukloniBan ( $id) {
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token);

        if($user->admin){
            $korisnik= User::find($id);
            $korisnik->ban=0;
            $korisnik->save();
            return response()->json(['success' => 'Ban removed'], HttpResponse::HTTP_OK);
        }
        else return response()->json(['error' => 'No authorization to remove ban'], HttpResponse::HTTP_UNAUTHORIZED);
    }

    public function dajAdmine(){
        return User::where('admin', true)->get();
    }

    public function dajAdmina($id){
        return User::where('admin', true)
            ->where('id', $id)->get();
    }
}