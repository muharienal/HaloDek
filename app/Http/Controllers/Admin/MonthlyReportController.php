<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonthlyReportController extends Controller
{
    public function __construct(private MonthlyReport $monthly) {}

    public function index(Request $request): View
    {
        $month = MonthlyReport::parse($request->query('bulan'));

        return view('admin.monthly.index', [
            'd' => $this->monthly->build($request->user(), $month),
            'months' => $this->options($month),
            'selected' => $month->format('Y-m'),
        ]);
    }

    public function print(Request $request): View
    {
        return view('admin.monthly.print', ['d' => $this->monthly->build($request->user(), MonthlyReport::parse($request->query('bulan')))]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $month = MonthlyReport::parse($request->query('bulan'));
        $data = $this->monthly->build($request->user(), $month);
        $path = \App\Support\Xlsx::build($this->monthly->sheets($data));

        return response()->download($path, 'laporan-bulanan-halodek-'.$month->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** Pilihan bulan; bulan yang sedang dilihat selalu ada walau lebih lama dari 12 bulan. */
    private function options($month): array
    {
        $opts = MonthlyReport::monthOptions();
        $opts[$month->format('Y-m')] ??= $month->translatedFormat('F Y');
        krsort($opts);

        return $opts;
    }
}
