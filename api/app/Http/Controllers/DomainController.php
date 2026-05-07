<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DomainController extends Controller
{
    // List domains for current user
    public function fetchDomains(Request $request)
    {
        $perPage = $request['perPage'];
        $page = $request['page'];
        $sortBy = $request['sortBy'];
        $sortDesc = $request['sortDesc']; 
        $rangePicker = $request['rangePicker'];
        $startDate = '';
        $endDate = '';
        $status = $request['status'];
        
        $q = $request['q'];

        $status = $request['status'];

        $q = $q ?? ''; 
        $status = $status ?? '';

        if(strlen($rangePicker)) {

            if (strpos($rangePicker, "to") !== false) {
                $rangePicker = str_replace(' ', '', $rangePicker);
                $rangePicker = explode('to', $rangePicker);
                
                $startDate =$rangePicker[0];
                $endDate =$rangePicker[1];
            }            
        }

        $domains = Auth::user()->domains()->where(function ($query) use ($q, $status) {
            $query->whereRaw('domain like ?', ['%' . $q . '%']);
        });

       
                
	    $domains = $domains->orderBy($sortBy, $sortDesc ? 'desc' : 'asc')
                                ->paginate($perPage, ['*'], 'page', $page);

        $total_rows = $domains->total();
        $domains = $domains->items();  

        return response()->json([
            "domains" => $domains,
            "total" => $total_rows
        ]);

    }

    // Add new domain
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|unique:domains,domain',
        ]);

        $domain = Domain::create([
            'user_id' => Auth::id(),
            'domain' => $request->domain,
            'verification_token' => Domain::generateToken(),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'domain' => $domain,
        ]);
    }

    // Show domain details
    public function show(Domain $domain)
    {
        $this->authorize('view', $domain);

        return response()->json($domain);
    }

    // Verify domain (DNS / File / Meta)
    public function verify(Request $request, Domain $domain)
    {
        $this->authorize('update', $domain);

        $request->validate([
            'method' => 'required|in:dns,file,meta',
        ]);

        $method = $request->method;
        $domain->verification_method = $method;

        $verified = false;

        if ($method === 'dns') {
            $verified = $this->verifyDNS($domain);
        } elseif ($method === 'file') {
            $verified = $this->verifyFile($domain);
        } elseif ($method === 'meta') {
            $verified = $this->verifyMeta($domain);
        }

        if ($verified) {
            $domain->status = 'verified';
            $domain->verified_at = now();
            $domain->save();

            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully',
                'domain' => $domain,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Verification failed. Please try again.',
        ], 422);
    }

    // --- Verification helpers ---
    protected function verifyDNS(Domain $domain): bool
    {
        $records = dns_get_record($domain->domain, DNS_TXT);

        foreach ($records as $record) {
            if (isset($record['txt']) && str_contains($record['txt'], $domain->verification_token)) {
                return true;
            }
        }

        return false;
    }

    protected function verifyFile(Domain $domain): bool
    {
        try {
            $url = "https://{$domain->domain}/sentrigate.txt";
            $response = Http::timeout(5)->get($url);

            return $response->ok() && str_contains($response->body(), $domain->verification_token);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function verifyMeta(Domain $domain): bool
    {
        try {
            $url = "https://{$domain->domain}";
            $html = Http::timeout(5)->get($url)->body();

            return str_contains($html, $domain->verification_token);
        } catch (\Exception $e) {
            return false;
        }
    }
}
