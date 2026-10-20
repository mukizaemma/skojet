@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
@php $invoice = hotel_invoice($setting); @endphp
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-2">Invoice details</h4>
        <p class="text-muted">Printed at the top and bottom of each invoice. Leave a field blank to use the hotel name, address, phone, and email already on the website.</p>
        <section class="bg-white border rounded p-4">
            <form method="POST" action="{{ route('content-management.front-office.settings.invoice') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_legal_name">Name on the invoice</label>
                        <input type="text" class="form-control" id="invoice_legal_name" name="invoice_legal_name" maxlength="255" value="{{ old('invoice_legal_name', $setting->invoice_legal_name ?? '') }}" placeholder="{{ $setting->company }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_tax_id">Tax ID</label>
                        <input type="text" class="form-control" id="invoice_tax_id" name="invoice_tax_id" maxlength="80" value="{{ old('invoice_tax_id', $setting->invoice_tax_id ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="invoice_address">Address</label>
                        <textarea class="form-control" id="invoice_address" name="invoice_address" rows="2" maxlength="500">{{ old('invoice_address', $setting->invoice_address ?? '') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_phone">Phone</label>
                        <input type="text" class="form-control" id="invoice_phone" name="invoice_phone" maxlength="40" value="{{ old('invoice_phone', $setting->invoice_phone ?? '') }}" placeholder="{{ $invoice['phone'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_email">Email</label>
                        <input type="email" class="form-control" id="invoice_email" name="invoice_email" maxlength="255" value="{{ old('invoice_email', $setting->invoice_email ?? '') }}" placeholder="{{ $setting->email }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="invoice_footer">Footer note</label>
                        <textarea class="form-control" id="invoice_footer" name="invoice_footer" rows="3" maxlength="1000" placeholder="Bank details, payment terms, or a thank-you line">{{ old('invoice_footer', $setting->invoice_footer ?? '') }}</textarea>
                    </div>
                </div>
                <button class="btn btn-dark mt-3" type="submit">Save invoice details</button>
            </form>
        </section>
    </div>
</div>
@endsection
