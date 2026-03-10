<?php

namespace App\Http\Controllers\Api;

use App\Constants\CmsContentType;
use App\Http\Controllers\Controller;
use App\Models\housingCms;
use App\Support\Concerns\HandlesCmsContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public CMS content by type (no auth).
 * Returns list for faq, notice, user_manual; single record for about_us, contact_us, what_is_new.
 */
class CmsContentPublicController extends Controller
{
    use HandlesCmsContent;

    public function show(Request $request, string $type): JsonResponse
    {
        $type = $this->normalizeContentType($type);

        if (!CmsContentType::isValid($type)) {
            return response()->json([], 200);
        }

        $query = housingCms::where('content_type', $type)
            ->where('is_active', 1)
            ->orderBy('order_no')
            ->orderBy('housing_cms_id', 'desc');

        if (in_array($type, CmsContentType::LIST_TYPES, true)) {
            $items = $query->get()->map(fn ($item) => $this->formatCmsContent($item));
            return response()->json($items->values()->all());
        }

        $record = $query->first();

        if (!$record) {
            return response()->json([]);
        }

        return response()->json($this->formatCmsContent($record));
    }
}

