<?php

namespace App\Http\Controllers;

use App\Models\BoardingHouse;
use App\Models\BoardingHouseImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BoardingHouseImageController extends Controller
{
    /**
     * Upload multiple images for a boarding house
     */
    public function upload(Request $request, $boardingHouseId)
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $boardingHouse = BoardingHouse::findOrFail($boardingHouseId);
        
        // Create directory if not exists
        $uploadPath = public_path('assets/' . $boardingHouseId);
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $uploadedImages = [];
        $currentMaxOrder = BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
            ->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $image) {
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $boardingHouseId . '/' . $imageName;
            
            // Move file to public/assets/{boarding_house_id}/
            $image->move($uploadPath, $imageName);

            // Save to database
            $boardingHouseImage = BoardingHouseImage::create([
                'boarding_house_id' => $boardingHouseId,
                'image_path' => $imagePath,
                'image_name' => $imageName,
                'sort_order' => $currentMaxOrder + $index + 1,
                'is_primary' => $index === 0 && !$boardingHouse->images()->exists(), // First image is primary if no images exist
            ]);

            $uploadedImages[] = [
                'id' => $boardingHouseImage->id,
                'image_url' => $boardingHouseImage->image_url,
                'is_primary' => $boardingHouseImage->is_primary,
                'sort_order' => $boardingHouseImage->sort_order,
            ];
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages
        ]);
    }

    /**
     * Get all images for a boarding house
     */
    public function index($boardingHouseId)
    {
        $boardingHouse = BoardingHouse::findOrFail($boardingHouseId);
        
        $images = $boardingHouse->images()->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ];
        });

        return response()->json([
            'boarding_house_id' => $boardingHouseId,
            'images' => $images
        ]);
    }

    /**
     * Set primary image
     */
    public function setPrimary(Request $request, $boardingHouseId, $imageId)
    {
        $boardingHouse = BoardingHouse::findOrFail($boardingHouseId);
        
        // Remove primary status from all images
        BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
            ->update(['is_primary' => false]);
        
        // Set new primary image
        $image = BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
            ->where('id', $imageId)
            ->firstOrFail();
        
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image updated successfully',
            'image' => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'is_primary' => true,
            ]
        ]);
    }

    /**
     * Delete an image
     */
    public function destroy($boardingHouseId, $imageId)
    {
        $image = BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
            ->where('id', $imageId)
            ->firstOrFail();

        // Delete physical file
        $filePath = public_path('assets/' . $image->image_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // If this was primary image, set another image as primary
        if ($image->is_primary) {
            $nextImage = BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
                ->where('id', '!=', $imageId)
                ->orderBy('sort_order')
                ->first();
            
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully'
        ]);
    }

    /**
     * Update image sort order
     */
    public function updateOrder(Request $request, $boardingHouseId)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*.id' => 'required|exists:boarding_house_images,id',
            'images.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->images as $imageData) {
            BoardingHouseImage::where('boarding_house_id', $boardingHouseId)
                ->where('id', $imageData['id'])
                ->update(['sort_order' => $imageData['sort_order']]);
        }

        return response()->json([
            'message' => 'Image order updated successfully'
        ]);
    }
}