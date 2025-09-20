@extends('layouts.guest')

@section('content')
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-5">
    <div class="card login-card mx-auto">
      <div class="card-body p-4">
        <h5 class="card-title mb-3 text-center">Sign in</h5>
        @if ($errors->any())
          <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login.post') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', 'test@example.com') }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" value="password" required>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary">Login</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
