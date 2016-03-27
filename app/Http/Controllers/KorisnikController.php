<?php

namespace App\Http\Controllers;

use App\Models\Korisnik;
use App\Models\KorisnikDodatno;
use Illuminate\Http\Request;

use App\Http\Requests;

class KorisnikController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       return Korisnik::all() ;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       $korisnik=new Korisnik() ;
        $korisnik->ime=$request->ime;
        $korisnik->prezime=$request->prezime;
        $korisnik->email=$request->email;
        $korisnik->lozinka=$request->lozinka;
        $korisnik->verifikovan=$request->verifikovan;
        $korisnik->ban=$request->ban;
         $korisnik->vrijeme_bana=$request->vrijeme_bana;
        $korisnik->create_time=$request->create_time;

        if (isset($request->grad) || isset($request->telefon) || isset ($request->drzava))

        {
            $dodatno=new KorisnikDodatno();
            $dodatno->telefon=$request->telefon;
            $dodatno->grad=$request->grad;
            $dodatno->drzava=$request->drzava;
            $dodatno->save();
            $korisnik->dodatno_korisnik=$dodatno->id;
       }
        $korisnik->save() ;


    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Korisnik::find($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        $input=$request->all();
        $korisnik=Korisnik::find($id) ;
        $korisnik->fill($input);
        $korisnik->save();

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function PoEmail($email)
    {
     return Korisnik::where('email', $email)->get();
    }
    public function urediPoEmail(Request $request,  $email)
    {
        $korisnik= Korisnik::where('email', $email)->first();
        $input=$request->all();

        $korisnik->fill($input);
        $korisnik->save();

    }

   
}