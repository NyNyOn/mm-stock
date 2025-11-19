<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLdapUserRequest;
use App\Http\Requests\UpdateLdapUserRequest;
use App\Models\LdapUser;

class LdapUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLdapUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(LdapUser $ldapUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LdapUser $ldapUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLdapUserRequest $request, LdapUser $ldapUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LdapUser $ldapUser)
    {
        //
    }
}
