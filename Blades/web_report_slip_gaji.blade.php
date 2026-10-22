@php
  $req = app()->request;
  $periode_from = $req->periode_from ?? '2023-01-01';
  $periode_to = $req->periode_to ?? '2023-12-30';
  $raw = \DB::select("
    select f.periode_awal, f.nomor nomor_gaji, f.periode_akhir, f.desc, k.nama_lengkap, k.nik, kd.nama dir, kdi.nama divisi, kde.nama dept, r.* 
    from t_final_gaji_det_rincian r
    join t_final_gaji_det d on d.id = r.t_final_gaji_det_id 
    join m_kary k on k.id = d.m_kary_id 
    join t_final_gaji f on f.id = d.t_final_gaji_id 
    left join m_dir kd on kd.id = k.m_dir_id 
    left join m_divisi kdi on kdi.id = k.m_divisi_id 
    left join m_dept kde on kde.id = k.m_dept_id  
    where d.m_kary_id = ? and f.id = coalesce(?, f.id)
    order by r.seq;
  ", [ $req->m_kary_id, $req->f_id ]);

  function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
  }

  $active_filters = [];
  if (!empty($req->f_id)) {
    $fg = \DB::table('t_final_gaji')->where('id', $req->f_id)->first();
    if ($fg) $active_filters['Final Gaji'] = $fg->nomor . ($fg->desc ? ' (' . $fg->desc . ')' : '');
  }
  if (!empty($req->m_kary_id)) {
    $kary = \DB::table('m_kary')->where('id', $req->m_kary_id)->first();
    if ($kary) $active_filters['Karyawan'] = ($kary->nik ? $kary->nik . ' - ' : '') . $kary->nama_lengkap;
  }
  if (!empty($req->m_dir_id)) {
    $dirName = \DB::table('m_dir')->where('id', $req->m_dir_id)->value('nama');
    if ($dirName) $active_filters['Direktorat'] = $dirName;
  }
  if (!empty($req->m_divisi_id)) {
    $divName = \DB::table('m_divisi')->where('id', $req->m_divisi_id)->value('nama');
    if ($divName) $active_filters['Divisi'] = $divName;
  }
  if (!empty($req->m_dept_id)) {
    $deptName = \DB::table('m_dept')->where('id', $req->m_dept_id)->value('nama');
    if ($deptName) $active_filters['Departemen'] = $deptName;
  }
@endphp

@if(!count($raw))
  <div style="font-family: Arial, sans-serif; padding: 20px; text-align: center; color: #64748b; font-style: italic;">
    Data slip gaji tidak ditemukan untuk karyawan dan periode final gaji yang dipilih.
  </div>
@else
<div style="width: 100%; max-width: 800px; font-family: Arial, Helvetica, sans-serif; margin-bottom: 12px;">
  <div style="text-align: center; margin-bottom: 6px;">
    <span style="font-size: 14pt; font-weight: bold; display: block; color: #1e293b; letter-spacing: 0.5px;">SLIP GAJI KARYAWAN</span>
    @if(isset($raw[0]->periode_awal) && isset($raw[0]->periode_akhir))
      <span style="font-size: 10pt; color: #64748b; display: block; margin-top: 2px;">
        Periode: {{ date('d-m-Y', strtotime($raw[0]->periode_awal)) }} s/d {{ date('d-m-Y', strtotime($raw[0]->periode_akhir)) }}
      </span>
    @endif
  </div>

  @if(count($active_filters) > 0)
  <table width="100%" style="border-collapse: collapse; margin-top: 4px; margin-bottom: 10px;">
    <tr>
      <td style="text-align: left; vertical-align: bottom; border: none; font-size: 9pt; line-height: 1.5; color: #334155; padding: 0;">
        @foreach($active_filters as $fLabel => $fVal)
          <div><strong style="color: #0f172a;">{{ $fLabel }}</strong> = <span style="color: #334155;">{{ $fVal }}</span></div>
        @endforeach
      </td>
    </tr>
  </table>
  @endif

  <table style="border-collapse: collapse; width: 100%; font-size: 10pt; margin-bottom: 14px; background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;" cellpadding="6">
    <tr>
      <td style="width: 50%; vertical-align: top; border-right: 1px solid #e2e8f0;">
        <table style="border-collapse: collapse; width: 100%; font-size: 9pt;" cellpadding="2">
          <tr>
            <td style="width: 30%; color: #64748b; font-weight: 600;">NIK</td>
            <td style="color: #0f172a;">: {{ $raw[0]->nik }}</td>
          </tr>
          <tr>
            <td style="color: #64748b; font-weight: 600;">Nama Karyawan</td>
            <td style="color: #0f172a; font-weight: bold;">: {{ $raw[0]->nama_lengkap }}</td>
          </tr>
          <tr>
            <td style="color: #64748b; font-weight: 600;">Direktorat</td>
            <td style="color: #0f172a;">: {{ $raw[0]->dir ?? '-' }}</td>
          </tr>
          <tr>
            <td style="color: #64748b; font-weight: 600;">Divisi</td>
            <td style="color: #0f172a;">: {{ $raw[0]->divisi ?? '-' }}</td>
          </tr>
          <tr>
            <td style="color: #64748b; font-weight: 600;">Departemen</td>
            <td style="color: #0f172a;">: {{ $raw[0]->dept ?? '-' }}</td>
          </tr>
        </table>
      </td>
      <td style="width: 50%; vertical-align: top;">
        <table style="border-collapse: collapse; width: 100%; font-size: 9pt;" cellpadding="2">
          <tr>
            <td style="width: 30%; color: #64748b; font-weight: 600;">No. Final Gaji</td>
            <td style="color: #0f172a; font-weight: 600;">: {{ $raw[0]->nomor_gaji }}</td>
          </tr>
          <tr>
            <td style="color: #64748b; font-weight: 600;">Catatan</td>
            <td style="color: #0f172a;">: {{ $raw[0]->desc ?? '-' }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <table width="100%" style="font-size: 10px; font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;" cellpadding="5" id="lastTable">
    <thead>
      <tr style="background-color: #e2e8f0; color: #0f172a;">
        <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: left; padding: 6px 8px;">Komponen</th>
        <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: right; padding: 6px 8px;">Nilai</th>
        <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: left; padding: 6px 8px;">Catatan</th>
      </tr>
    </thead>
    <tbody>
      @foreach($raw as $i => $d)
      @php
          $isTotalRow = ($d->factor == '=');
          $backgroundColor = $isTotalRow ? '#e2e8f0' : ($i % 2 === 1 ? '#f8fafc' : '#ffffff'); 
          $rowWeight = $isTotalRow ? 'font-weight: bold; color: #0f172a;' : 'color: #334155;';
      @endphp
      <tr style="background-color: {{$backgroundColor}}; {{$rowWeight}}">
        <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 5px 8px;">{{ $d->label }}</td>
        <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 5px 8px;">{{ ($d->factor == '-' ? '(-)' : '').formatRupiah($d->value) }}</td>
        <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 5px 8px;">{{ $d->deskripsi }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif