@extends('errors.layout')

@section('code', '429')
@section('title', 'Terlalu banyak permintaan')

@section('message')
    <p>Terlalu banyak permintaan dalam tempoh yang singkat.</p>
    <p>Sila tunggu sebentar sebelum mencuba semula.</p>
@endsection
