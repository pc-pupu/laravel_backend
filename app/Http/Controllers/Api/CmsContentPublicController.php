<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\housingCms;
use App\Support\Concerns\HandlesCmsContent;
use Illuminate\Http\Request;

class CmsContentPublicController extends Controller
{
    use HandlesCmsContent;

    public function show(Request $request, string $type)
    {
        $type = $this->normalizeContentType($type);

        $query = housingCms::where('content_type', $type)
            ->where('is_active', 1)
            ->orderBy('order_no')
            ->orderBy('housing_cms_id', 'desc');

        if (in_array($type, ['faq', 'notice'], true)) {
            $items = $query->get()->map(fn ($item) => $this->formatCmsContent($item));
            return response()->json($items);
        }

        $record = $query->first();

        if (!$record) {
            return response()->json([]);
        }

        return response()->json($this->formatCmsContent($record));
    }
}

