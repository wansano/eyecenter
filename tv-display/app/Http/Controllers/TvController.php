<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TvController extends Controller
{
    private function buildTvPayload(): array
    {
        $now = Carbon::now();
        $displayFrom = (clone $now)->subMinutes(5);
        $today = $now->toDateString();

        $clinicName = null;
        try {
            $clinicRow = DB::table('profil_entreprise')->orderBy('id')->first();
            if ($clinicRow && isset($clinicRow->denomination)) {
                $clinicName = (string) $clinicRow->denomination;
            }
        } catch (\Throwable $e) {
            // Ignore si la table n'existe pas ou si la connexion DB n'est pas encore prête.
        }

        $clinicName = $clinicName ?: config('app.name');

        $baseQuery = DB::table('dmd_rendez_vous as r')
            ->join('patients as p', 'p.id_patient', '=', 'r.id_patient')
            ->leftJoin('traitements as t', 't.id_type', '=', 'r.motif')
            ->leftJoin('users as u', 'u.id', '=', 'r.traitant')
            ->select([
                'r.id_rdv',
                'r.prochain_rdv',
                'r.motif',
                't.nom_type as motif_label',
                'r.traitant',
                'u.pseudo as medecin',
                'p.nom_patient',
                'p.id_patient',
            ])
            ->whereIn('r.status', [0, 1, 2, 4])
            ->orderBy('r.prochain_rdv');

        $hasAnyToday = DB::table('dmd_rendez_vous as r')
            ->whereIn('r.status', [0, 1, 2, 4])
            ->whereDate('r.prochain_rdv', $today)
            ->exists();

        // 1) Tant qu'il reste un RDV aujourd'hui (en tenant compte du maintien 5 min), on affiche le prochain créneau d'aujourd'hui.
        $nextAtToday = DB::table('dmd_rendez_vous as r')
            ->whereIn('r.status', [0, 1, 2, 4])
            ->whereDate('r.prochain_rdv', $today)
            ->where('r.prochain_rdv', '>=', $displayFrom)
            ->orderBy('r.prochain_rdv')
            ->value('r.prochain_rdv');

        $slotTime = null;
        $rdvs = collect();
        $message = null;

        if ($nextAtToday) {
            $slotTime = Carbon::parse($nextAtToday);
        } else {
            // 2) Si les RDV du jour sont finis, on affiche un message jusqu'à 30 min avant le prochain RDV futur.
            $nextAtFuture = DB::table('dmd_rendez_vous as r')
                ->whereIn('r.status', [0, 1, 2, 4])
                ->where('r.prochain_rdv', '>=', $now)
                ->orderBy('r.prochain_rdv')
                ->value('r.prochain_rdv');

            if ($hasAnyToday) {
                if ($nextAtFuture) {
                    $futureTime = Carbon::parse($nextAtFuture);
                    $showFrom = (clone $futureTime)->subMinutes(30);
                    if ($now->greaterThanOrEqualTo($showFrom)) {
                        $slotTime = $futureTime;
                    } else {
                        $message = 'Fin des rendez-vous du jour — Prochain rendez-vous le '.$futureTime->format('d/m/Y').' à '.$futureTime->format('H:i');
                    }
                } else {
                    $message = 'Fin des rendez-vous du jour';
                }
            } else {
                // Aucun RDV aujourd'hui
                $message = 'Aucun rendez-vous';
                if ($nextAtFuture) {
                    $futureTime = Carbon::parse($nextAtFuture);
                    $showFrom = (clone $futureTime)->subMinutes(30);
                    if ($now->greaterThanOrEqualTo($showFrom)) {
                        $slotTime = $futureTime;
                        $message = null;
                    } else {
                        $message = 'Prochain RDV le '.$futureTime->format('d/m/Y').' à '.$futureTime->format('H:i');
                    }
                }
            }
        }

        if ($slotTime) {
            $start = (clone $slotTime)->startOfMinute();
            $end = (clone $slotTime)->endOfMinute();

            $rdvs = (clone $baseQuery)
                ->whereBetween('r.prochain_rdv', [$start, $end])
                ->orderBy('r.prochain_rdv')
                ->orderBy('u.pseudo')
                ->get();
        }

        $slotIso = null;
        $time = null;
        if ($slotTime) {
            try {
                $time = $slotTime->format('H:i');
                $slotIso = $slotTime->toIso8601String();
            } catch (\Throwable $e) {
                $time = null;
                $slotIso = null;
            }
        }

        $rdvItems = [];
        foreach ($rdvs as $r) {
            $motif = $r->motif_label ?? ($r->motif ?? null);
            $rdvItems[] = [
                'id' => $r->id_rdv ?? null,
                'iso' => !empty($r->prochain_rdv) ? Carbon::parse($r->prochain_rdv)->toIso8601String() : null,
                'patientName' => (string)($r->nom_patient ?? ''),
                'patientId' => (string)($r->id_patient ?? ''),
                'motif' => (string)($motif ?? ''),
                'medecin' => (string)($r->medecin ?? ''),
            ];
        }

        return [
            'clinicName' => $clinicName,
            'slotTime' => $slotTime,
            'time' => $time,
            'slotIso' => $slotIso,
            'rdvItems' => $rdvItems,
            'message' => $message,
        ];
    }

    public function index(Request $request)
    {
        $payload = $this->buildTvPayload();

        return view('tv', [
            'clinicName' => $payload['clinicName'],
            'slotTime' => $payload['slotTime'],
            'time' => $payload['time'],
            'slotIso' => $payload['slotIso'],
            'rdvItems' => $payload['rdvItems'],
            'message' => $payload['message'],
            // Rafraîchissement via fetch() côté navigateur (sans reload)
            'refreshSeconds' => 15,
        ]);
    }

    public function data(Request $request)
    {
        $payload = $this->buildTvPayload();

        return response()->json([
            'clinicName' => $payload['clinicName'],
            'time' => $payload['time'],
            'slotIso' => $payload['slotIso'],
            'rdvItems' => $payload['rdvItems'],
            'message' => $payload['message'],
        ]);
    }
}
