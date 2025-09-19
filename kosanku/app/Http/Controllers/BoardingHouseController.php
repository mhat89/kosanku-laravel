<?php

namespace App\Http\Controllers;

use App\Models\BoardingHouse;
use Illuminate\Http\Request;

class BoardingHouseController extends Controller
{
    /**
     * GET /api/kosan/nearby?lat=...&lng=...&radius_km=3&q=...&limit=200
     * Mengambil daftar kos terdekat dengan opsi pencarian 'q'.
     */
    public function nearby(Request $r)
    {
        $data = $r->validate([
            'lat'       => 'required|numeric',
            'lng'       => 'required|numeric',
            'radius_km' => 'nullable|numeric|min:0',
            'q'         => 'nullable|string',
            'limit'     => 'nullable|integer|min:1|max:500',
        ]);

        $lat    = (float) $data['lat'];
        $lng    = (float) $data['lng'];
        $radius = (float) ($data['radius_km'] ?? 3.0);
        $limit  = (int)   ($data['limit'] ?? 200);
        $q      = trim($data['q'] ?? '');

        // Rumus haversine (dalam kilometer)
        $distanceSql = <<<SQL
(6371 * acos(
    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
  + sin(radians(?)) * sin(radians(latitude))
))
SQL;

        $builder = BoardingHouse::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'price_month'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("$distanceSql AS distance_km", [$lat, $lng, $lat]);

        if ($q !== '') {
            $builder->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('address', 'like', "%{$q}%");
            });
        }

        if ($radius > 0) {
            $builder->having('distance_km', '<=', $radius);
        }

        $items = $builder
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        return response()->json(['items' => $items]);
    }

    /**
     * GET /api/kosan/search?q=...&lat=...&lng=...&limit=10
     * Untuk autocomplete/suggestion di mobile.
     */
    public function search(Request $r)
    {
        $data = $r->validate([
            'q'     => 'required|string|min:1',
            'lat'   => 'nullable|numeric',
            'lng'   => 'nullable|numeric',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $q     = trim($data['q']);
        $limit = (int) ($data['limit'] ?? 10);
        $lat   = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng   = isset($data['lng']) ? (float) $data['lng'] : null;

        $builder = BoardingHouse::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'price_month'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('address', 'like', "%{$q}%");
            });

        if ($lat !== null && $lng !== null) {
            $distanceSql = <<<SQL
(6371 * acos(
    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
  + sin(radians(?)) * sin(radians(latitude))
))
SQL;
            $builder->selectRaw("$distanceSql AS distance_km", [$lat, $lng, $lat])
                    ->orderBy('distance_km');
        } else {
            $builder->orderBy('name');
        }

        $items = $builder->limit($limit)->get();

        return response()->json(['items' => $items]);
    }

    public function show($id)
    {
        $item = BoardingHouse::query()
            ->select(['id','name','address','latitude','longitude','price_month'])
            ->findOrFail($id);

        return response()->json($item);
    }
}
