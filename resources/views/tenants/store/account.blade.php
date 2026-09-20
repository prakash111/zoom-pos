@extends('tenants.store.layout')

@section('title', __('Profile') . ' — ' . ($company->name ?? 'Storefront'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6 py-4"
     x-data="{
         accountTab: 'account',
         isEditingProfile: false,
         passwordForm: { current_password: '', new_password: '', confirm_password: '' },
         passwordMessage: '',
         passwordError: '',
         returnFormOpen: false,
         selectedOrderForReturn: null,
         returnReason: 'Wrong item received',
         returnComment: '',
         reviewModalOpen: false,
         reviewOrder: null,
         reviewRating: 5,
         reviewTitle: '',
         reviewComment: '',
         reviewError: '',
         isSubmittingReview: false,
         isLoadingReviews: false,
         selectedProductForReview: null,
         userReviews: [],
         unreviewedProducts: [],
         activeFilterOrders: 'all',

         get filteredOrders() {
             if (this.activeFilterOrders === 'all') return this.ordersList;
             return this.ordersList.filter(o => (o.tracking_status || o.status || '').toLowerCase() === this.activeFilterOrders.toLowerCase());
         },

         get cancelledOrders() {
             return this.ordersList.filter(o => ['cancelled', 'returned', 'refunded'].includes((o.tracking_status || o.status || '').toLowerCase()));
         },

         async loadUserReviews() {
             this.isLoadingReviews = true;
             try {
                 const res = await fetch('/store/customer/reviews?store=' + encodeURIComponent(this.companySlug || ''), {
                     headers: this.authHeaders()
                 });
                 if (res.ok) {
                     const data = await res.json();
                     if (data.success) {
                         this.userReviews = data.reviews || [];
                         this.unreviewedProducts = data.unreviewed_products || [];
                     }
                 }
             } catch (e) {
                 console.error('Failed to load user reviews', e);
             } finally {
                 this.isLoadingReviews = false;
             }
         },

         openReviewModal(product) {
             this.selectedProductForReview = product;
             this.reviewRating = 5;
             this.reviewTitle = '';
             this.reviewComment = '';
             this.reviewError = '';
             this.reviewModalOpen = true;
         },

         closeReviewModal() {
             this.reviewModalOpen = false;
             this.selectedProductForReview = null;
         },

         async submitReview() {
             if (!this.selectedProductForReview) return;
             if (!this.reviewComment.trim()) {
                 this.reviewError = '{{ __('Please share a few words about your experience.') }}';
                 return;
             }
             this.isSubmittingReview = true;
             this.reviewError = '';
             try {
                 const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                 const headers = this.authHeaders();
                 if (token) headers['X-CSRF-TOKEN'] = token;

                 const pId = this.selectedProductForReview.id || this.selectedProductForReview.product_id;
                 const res = await fetch('/store/products/' + pId + '/reviews?store=' + encodeURIComponent(this.companySlug || ''), {
                     method: 'POST',
                     headers: headers,
                     body: JSON.stringify({
                         rating: this.reviewRating,
                         title: this.reviewTitle.trim() || null,
                         comment: this.reviewComment.trim()
                     })
                 });
                 const data = await res.json();
                 if (res.ok && data.success) {
                     alert(data.message || '{{ __('Thank you! Your review has been submitted.') }}');
                     this.closeReviewModal();
                     await this.loadUserReviews();
                 } else {
                     this.reviewError = data.message || '{{ __('Failed to submit review.') }}';
                 }
             } catch (e) {
                 console.error('Review submit error', e);
                 this.reviewError = '{{ __('Network error while submitting review.') }}';
             } finally {
                 this.isSubmittingReview = false;
             }
         },

         async submitPasswordChange() {
             this.passwordError = '';
             this.passwordMessage = '';
             if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                 this.passwordError = '{{ __('New passwords do not match.') }}';
                 return;
             }
             if (this.passwordForm.new_password.length < 6) {
                 this.passwordError = '{{ __('Password must be at least 6 characters.') }}';
                 return;
             }
             try {
                 const res = await fetch('/api/storefront/customer/profile?store=' + encodeURIComponent(this.companySlug || ''), {
                     method: 'PUT',
                     headers: this.authHeaders(),
                     body: JSON.stringify({
                         current_password: this.passwordForm.current_password,
                         new_password: this.passwordForm.new_password
                     })
                 });
                 const data = await res.json();
                 if (data.success) {
                     this.passwordMessage = '{{ __('Password changed successfully!') }}';
                     this.passwordForm = { current_password: '', new_password: '', confirm_password: '' };
                     setTimeout(() => { this.passwordMessage = ''; }, 4000);
                 } else {
                     this.passwordError = data.message || '{{ __('Failed to update password.') }}';
                 }
             } catch (e) {
                 this.passwordError = '{{ __('Network error while updating password.') }}';
             }
         },

         async submitProfileForm() {
             await this.updateCustomerProfile();
             this.isEditingProfile = false;
         }
     }"
     x-init="
         if (customerToken) loadUserReviews();
         $watch('accountTab', tab => {
             if (tab === 'reviews') loadUserReviews();
         });
     ">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
        <a href="{{ url('/') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition flex items-center gap-1">
            <span>🏠</span> {{ __('Home') }}
        </a>
        <span>/</span>
        <span class="text-slate-900 dark:text-white font-extrabold">{{ __('Profile') }}</span>
    </nav>

    <!-- Page Title -->
    <div class="flex items-center justify-between pb-2">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            {{ __('Profile') }}
        </h1>
        <template x-if="customer">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                {{ __('Logged in as') }} <strong class="text-slate-900 dark:text-white" x-text="customer.email || customer.phone"></strong>
            </span>
        </template>
    </div>

    <!-- 1. GUEST / NOT SIGNED IN STATE -->
    <template x-if="!customer">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-8 sm:p-12 shadow-sm border border-slate-200/80 dark:border-slate-800 text-center max-w-lg mx-auto space-y-6">
            <div class="w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto text-4xl shadow-inner">
                👤
            </div>
            <div class="space-y-1.5">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ __('Sign in to Your Account') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Access your orders, track deliveries in real time, view ratings, and manage personal information.') }}
                </p>
            </div>

            <div class="space-y-3 pt-2">
                <button type="button"
                        x-on:click="openAuthModal('login')"
                        class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs tracking-wide shadow-lg shadow-emerald-500/20 active:scale-98 transition cursor-pointer">
                    {{ __('Sign In to Account') }}
                </button>
                <button type="button"
                        x-on:click="openAuthModal('register')"
                        class="w-full py-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs transition cursor-pointer">
                    {{ __('Create New Account') }}
                </button>
            </div>

            <template x-if="enableGoogleLogin">
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2.5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('Or continue with') }}</p>
                    <a :href="googleAuthUrl"
                       class="w-full py-2.5 px-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-2.5 transition active:scale-98 shadow-2xs">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>{{ __('Sign In with Google') }}</span>
                    </a>
                </div>
            </template>
        </div>
    </template>

    <!-- 2. AUTHENTICATED USER DASHBOARD (Matching user-dashboard-should-look-like-this.webp) -->
    <template x-if="customer">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

            <!-- LEFT SIDEBAR (Width: 1 Col) -->
            <div class="lg:col-span-1 space-y-4">
                
                <!-- Hello Card -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="relative">
                        <template x-if="customer.avatar_url">
                            <img :src="customer.avatar_url" class="w-14 h-14 rounded-full object-cover border-2 border-emerald-500 shadow-xs">
                        </template>
                        <template x-if="!customer.avatar_url">
                            <div class="w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-black text-xl flex items-center justify-center border-2 border-amber-300 dark:border-amber-700 uppercase shadow-inner">
                                <span x-text="(customer.name || 'U').charAt(0)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-400 font-medium leading-none mb-1">{{ __('Hello') }}</p>
                        <h3 class="text-base font-black text-slate-900 dark:text-white truncate" x-text="customer.name"></h3>
                    </div>
                </div>

                <!-- Navigation Menu Card (Matches user-dashboard-should-look-like-this.webp) -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-3 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
                    
                    <!-- 1. My Accounts -->
                    <button type="button"
                            x-on:click="accountTab = 'account'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'account' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>{{ __('My Accounts') }}</span>
                        </div>
                        <template x-if="accountTab === 'account'">
                            <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                        </template>
                    </button>

                    <!-- 2. My Orders -->
                    <button type="button"
                            x-on:click="accountTab = 'orders'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'orders' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>{{ __('My Orders') }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black"
                              :class="accountTab === 'orders' ? 'bg-blue-700 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                              x-text="ordersList.length"></span>
                    </button>

                    <!-- 3. Returns & Cancel -->
                    <button type="button"
                            x-on:click="accountTab = 'returns'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'returns' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            <span>{{ __('Returns & Cancel') }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black"
                              :class="accountTab === 'returns' ? 'bg-blue-700 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                              x-text="cancelledOrders.length"></span>
                    </button>

                    <!-- 4. My Rating & Reviews -->
                    <button type="button"
                            x-on:click="accountTab = 'reviews'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'reviews' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                            <span>{{ __('My Rating & Reviews') }}</span>
                        </div>
                    </button>

                    <!-- 5. My Wishlist -->
                    <button type="button"
                            x-on:click="accountTab = 'wishlist'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'wishlist' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            <span>{{ __('My Wishlist') }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black"
                              :class="accountTab === 'wishlist' ? 'bg-blue-700 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                              x-text="wishlistItems.length"></span>
                    </button>

                    <!-- 6. Payment -->
                    <button type="button"
                            x-on:click="accountTab = 'payment'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'payment' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <span>{{ __('Payment') }}</span>
                        </div>
                    </button>

                    <!-- 7. Change Password -->
                    <button type="button"
                            x-on:click="accountTab = 'password'"
                            class="w-full text-left px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center justify-between cursor-pointer"
                            :class="accountTab === 'password' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span>{{ __('Change Password') }}</span>
                        </div>
                    </button>

                    <!-- Sign Out Button -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                x-on:click="logoutCustomer()"
                                class="w-full text-left px-4 py-2.5 rounded-2xl text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition flex items-center gap-3 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>{{ __('Sign Out') }}</span>
                        </button>
                    </div>

                </div>
            </div>

            <!-- RIGHT MAIN CONTENT CARD (Width: 3 Cols) -->
            <div class="lg:col-span-3 bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800 shadow-sm min-h-[520px]">
                
                <!-- ============================================================ -->
                <!-- TAB 1: MY ACCOUNTS (PERSONAL INFORMATION) -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'account'" class="space-y-6">
                    
                    <!-- Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-5 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">
                            {{ __('Personal Information') }}
                        </h2>
                        
                        <!-- Toggle Edit Mode -->
                        <button type="button"
                                x-on:click="isEditingProfile = !isEditingProfile"
                                class="text-blue-600 hover:text-blue-700 text-xs font-black flex items-center gap-1.5 transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span x-text="isEditingProfile ? '{{ __('Cancel Editing') }}' : '{{ __('Change Profile Information') }}'"></span>
                        </button>
                    </div>

                    <template x-if="profileMessage">
                        <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center gap-2" x-text="profileMessage"></div>
                    </template>

                    <!-- Avatar with Edit Badge (Matching user-dashboard-should-look-like-this.webp) -->
                    <div class="flex items-center gap-5">
                        <div class="relative">
                            <div class="w-24 h-24 rounded-full bg-amber-400/90 dark:bg-amber-500/80 flex items-center justify-center overflow-hidden border-4 border-white dark:border-slate-800 shadow-md">
                                <template x-if="profileForm.avatar_url || customer.avatar_url">
                                    <img :src="profileForm.avatar_url || customer.avatar_url" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!profileForm.avatar_url && !customer.avatar_url">
                                    <span class="text-4xl text-amber-950 font-black" x-text="(customer.name || 'U').charAt(0)"></span>
                                </template>
                            </div>
                            
                            <!-- Pencil Edit Badge -->
                            <button type="button"
                                    x-on:click="isEditingProfile = true"
                                    title="{{ __('Edit avatar') }}"
                                    class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-emerald-600 text-white border-2 border-white dark:border-slate-900 flex items-center justify-center shadow-md hover:bg-emerald-700 transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-1">
                            <h3 class="text-base font-black text-slate-900 dark:text-white" x-text="customer.name"></h3>
                            <p class="text-xs text-slate-400 font-medium" x-text="customer.email || customer.phone"></p>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                {{ __('Verified Account') }}
                            </span>
                        </div>
                    </div>

                    <!-- Personal Info Form (Matches webp fields: Name, Date Of Birth, Gender, Phone Number, Email) -->
                    <form x-on:submit.prevent="submitProfileForm()" class="space-y-4 max-w-xl">
                        
                        <!-- Name Field -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Name') }}</label>
                            <input type="text"
                                   x-model="profileForm.name"
                                   :disabled="!isEditingProfile"
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 py-3 px-3.5 text-xs font-bold text-slate-900 dark:text-white transition"
                                   :class="!isEditingProfile ? 'bg-slate-50/70 dark:bg-slate-800/50 cursor-not-allowed text-slate-700' : 'bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500'">
                        </div>

                        <!-- Date Of Birth Field (with calendar icon) -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Date Of Birth') }}</label>
                            <div class="relative">
                                <input type="text"
                                       x-model="profileForm.date_of_birth"
                                       placeholder="DD/MM/YYYY"
                                       :disabled="!isEditingProfile"
                                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 py-3 px-3.5 pr-10 text-xs font-bold text-slate-900 dark:text-white transition"
                                       :class="!isEditingProfile ? 'bg-slate-50/70 dark:bg-slate-800/50 cursor-not-allowed text-slate-700' : 'bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500'">
                                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Gender Radio Group (Male / Female) -->
                        <div class="space-y-1.5 pt-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Gender') }}</label>
                            <div class="flex items-center gap-6">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200">
                                    <input type="radio"
                                           value="Male"
                                           x-model="profileForm.gender"
                                           :disabled="!isEditingProfile"
                                           class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                    <span>{{ __('Male') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200">
                                    <input type="radio"
                                           value="Female"
                                           x-model="profileForm.gender"
                                           :disabled="!isEditingProfile"
                                           class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                    <span>{{ __('Female') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Phone Number (with Country Code / Flag badge) -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Phone Number') }}</label>
                            <div class="flex items-center gap-2">
                                <div class="px-3 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 shrink-0">
                                    <span>🌐</span>
                                    <span>+90</span>
                                </div>
                                <input type="tel"
                                       x-model="profileForm.phone"
                                       :disabled="!isEditingProfile"
                                       placeholder="+90-123456789"
                                       class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 py-3 px-3.5 text-xs font-bold text-slate-900 dark:text-white transition"
                                       :class="!isEditingProfile ? 'bg-slate-50/70 dark:bg-slate-800/50 cursor-not-allowed text-slate-700' : 'bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500'">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Email') }}</label>
                            <input type="email"
                                   x-model="customer.email"
                                   disabled
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 py-3 px-3.5 text-xs font-bold text-slate-500 cursor-not-allowed">
                        </div>

                        <!-- Save Profile Action Buttons -->
                        <template x-if="isEditingProfile">
                            <div class="pt-3 flex items-center gap-3">
                                <button type="submit"
                                        class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition cursor-pointer">
                                    {{ __('Save Changes') }}
                                </button>
                                <button type="button"
                                        x-on:click="isEditingProfile = false"
                                        class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-extrabold text-xs transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </template>

                    </form>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 2: MY ORDERS -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'orders'" class="space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('My Orders') }}</h2>
                            <p class="text-xs text-slate-400">{{ __('Track active shipments, view receipts, and monitor progress.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" x-on:click="activeFilterOrders = 'all'" class="px-3 py-1.5 rounded-xl text-xs font-bold transition" :class="activeFilterOrders === 'all' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">{{ __('All') }}</button>
                            <button type="button" x-on:click="activeFilterOrders = 'confirmed'" class="px-3 py-1.5 rounded-xl text-xs font-bold transition" :class="activeFilterOrders === 'confirmed' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">{{ __('Active') }}</button>
                            <button type="button" x-on:click="activeFilterOrders = 'delivered'" class="px-3 py-1.5 rounded-xl text-xs font-bold transition" :class="activeFilterOrders === 'delivered' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">{{ __('Delivered') }}</button>
                        </div>
                    </div>

                    <template x-if="filteredOrders.length === 0">
                        <div class="py-16 text-center space-y-3">
                            <span class="text-4xl block">🛍️</span>
                            <h4 class="font-extrabold text-sm text-slate-800 dark:text-slate-200">{{ __('No orders found.') }}</h4>
                            <p class="text-xs text-slate-400">{{ __('You do not have any orders under this filter.') }}</p>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <template x-for="order in filteredOrders" :key="order.id">
                            <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:border-blue-400 transition space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/60 dark:border-slate-700/60">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono font-black text-sm text-slate-900 dark:text-white" x-text="'#' + order.sale_number"></span>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300"
                                                  x-text="order.tracking_status || order.status || 'Placed'"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-400" x-text="order.created_at"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a :href="'/store/track/' + (order.tracking_code || order.sale_number) + '?store=' + encodeURIComponent(companySlug)"
                                           class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-sm transition flex items-center gap-1.5">
                                            <span>🚚</span> {{ __('Track Order') }}
                                        </a>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="text-slate-500 dark:text-slate-400">
                                        <span class="font-bold text-slate-700 dark:text-slate-200" x-text="(order.items ? order.items.length : 1) + ' {{ __('items') }}'"></span>
                                        <span class="mx-2">&bull;</span>
                                        <span class="uppercase font-bold" x-text="order.payment_method || 'COD'"></span>
                                    </div>
                                    <div class="font-mono font-black text-base text-slate-900 dark:text-white">
                                        $<span x-text="parseFloat(order.total_amount || order.total || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 3: RETURNS & CANCEL -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'returns'" class="space-y-5">
                    <div class="pb-4 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('Returns & Cancel') }}</h2>
                        <p class="text-xs text-slate-400">{{ __('View eligible return items, refund statuses, and cancelled orders.') }}</p>
                    </div>

                    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-3">
                        <span class="text-xl">ℹ️</span>
                        <div class="space-y-0.5">
                            <h4 class="font-bold">{{ __('Store Return Policy') }}</h4>
                            <p class="text-[11px] text-amber-800 dark:text-amber-300">{{ __('Items can be returned or exchanged within 7 days of delivery. For damaged or defective goods, contact store support via WhatsApp or email.') }}</p>
                        </div>
                    </div>

                    <template x-if="cancelledOrders.length === 0">
                        <div class="py-16 text-center space-y-3">
                            <span class="text-4xl block">📦</span>
                            <h4 class="font-extrabold text-sm text-slate-800 dark:text-slate-200">{{ __('No returns or cancellations.') }}</h4>
                            <p class="text-xs text-slate-400">{{ __('You do not have any cancelled or returned orders at this time.') }}</p>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <template x-for="order in cancelledOrders" :key="order.id">
                            <div class="p-4 rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-black text-xs text-rose-900 dark:text-rose-200" x-text="'#' + order.sale_number"></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:text-rose-200">
                                        {{ __('Cancelled / Refunded') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500">
                                    <span x-text="order.created_at"></span>
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">$<span x-text="parseFloat(order.total_amount || 0).toFixed(2)"></span></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 4: MY RATING & REVIEWS -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'reviews'" class="space-y-6">
                    <div class="pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('My Rating & Reviews') }}</h2>
                            <p class="text-xs text-slate-400">{{ __('Share your feedback on purchased products and store service.') }}</p>
                        </div>
                        <button type="button"
                                x-on:click="loadUserReviews()"
                                class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                            <span :class="isLoadingReviews ? 'animate-spin' : ''">🔄</span>
                            <span>{{ __('Refresh') }}</span>
                        </button>
                    </div>

                    <!-- Delivered Products Awaiting Review Section -->
                    <div class="p-5 sm:p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 bg-amber-50/40 dark:bg-amber-950/20 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">⭐</span>
                                <h4 class="text-xs font-black text-amber-900 dark:text-amber-200 uppercase tracking-wider">{{ __('Products Awaiting Your Review') }}</h4>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200" x-text="unreviewedProducts.length"></span>
                        </div>

                        <template x-if="unreviewedProducts.length === 0">
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 text-center text-xs text-slate-500 dark:text-slate-400">
                                {{ __('All your delivered items have been reviewed. Thank you for your feedback!') }}
                            </div>
                        </template>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="item in unreviewedProducts" :key="item.id">
                                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200/80 dark:border-slate-800 flex items-center justify-between gap-3 shadow-xs">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-extrabold text-xs text-slate-900 dark:text-white truncate" x-text="item.name"></div>
                                        <div class="text-[11px] text-slate-400 mt-0.5" x-text="'Order #' + item.order_number"></div>
                                        <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-1" x-text="'$' + parseFloat(item.price || 0).toFixed(2)"></div>
                                    </div>
                                    <button type="button"
                                            x-on:click="openReviewModal(item)"
                                            class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-extrabold text-xs tracking-wide transition shadow-sm shrink-0 cursor-pointer flex items-center gap-1.5">
                                        <span>★</span>
                                        <span>{{ __('Rate Product') }}</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- My Submitted Reviews Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider">{{ __('My Submitted Reviews') }}</h4>
                            <span class="text-xs text-slate-400 font-bold" x-text="userReviews.length + ' {{ __('reviews') }}'"></span>
                        </div>

                        <template x-if="userReviews.length === 0">
                            <div class="py-10 text-center space-y-2 border border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-6">
                                <span class="text-3xl block">✍️</span>
                                <h4 class="font-extrabold text-xs text-slate-800 dark:text-slate-200">{{ __('No reviews submitted yet') }}</h4>
                                <p class="text-[11px] text-slate-400">{{ __('Rate items from your delivered orders above to share your thoughts!') }}</p>
                            </div>
                        </template>

                        <div class="space-y-3">
                            <template x-for="rev in userReviews" :key="rev.id">
                                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center text-amber-400 text-sm font-black">
                                                <template x-for="i in 5">
                                                    <span :class="i <= rev.rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-700'">★</span>
                                                </template>
                                            </div>
                                            <span class="font-extrabold text-xs text-slate-800 dark:text-slate-200" x-text="rev.rating + '/5'"></span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase"
                                              :class="rev.is_approved ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'">
                                            <span x-text="rev.is_approved ? '{{ __('Published') }}' : '{{ __('Pending Approval') }}'"></span>
                                        </span>
                                    </div>

                                    <div class="text-xs font-bold text-slate-900 dark:text-white" x-text="rev.product?.name || ('Product #' + rev.product_id)"></div>
                                    <template x-if="rev.title">
                                        <div class="text-xs font-extrabold text-slate-800 dark:text-slate-100" x-text="rev.title"></div>
                                    </template>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed" x-text="rev.comment"></p>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ {{ __('Verified Purchase') }}</span>
                                        <span x-text="new Date(rev.created_at).toLocaleDateString()"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 5: MY WISHLIST -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'wishlist'" class="space-y-5">
                    <div class="pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('My Wishlist') }}</h2>
                            <p class="text-xs text-slate-400">{{ __('Saved items for later purchase.') }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300" x-text="wishlistItems.length + ' {{ __('saved') }}'"></span>
                    </div>

                    <template x-if="wishlistItems.length === 0">
                        <div class="py-16 text-center space-y-3">
                            <span class="text-4xl block">🤍</span>
                            <h4 class="font-extrabold text-sm text-slate-800 dark:text-slate-200">{{ __('Your wishlist is empty.') }}</h4>
                            <p class="text-xs text-slate-400">{{ __('Explore products and tap the heart icon to save favorites.') }}</p>
                            <a href="{{ url('/') }}" class="inline-block mt-2 px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold text-xs">
                                {{ __('Discover Products') }}
                            </a>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <template x-for="item in wishlistItems" :key="item.id">
                            <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs flex flex-col justify-between space-y-3">
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 dark:text-white line-clamp-2" x-text="item.name"></h4>
                                    <div class="font-mono font-black text-sm text-emerald-600 dark:text-emerald-400 mt-1">
                                        $<span x-text="parseFloat(item.price || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <button type="button"
                                            x-on:click="addToCart(item, 1)"
                                            class="flex-1 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-xs transition cursor-pointer">
                                        {{ __('Add to Cart') }}
                                    </button>
                                    <button type="button"
                                            x-on:click="removeFromWishlist(item.id)"
                                            class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 transition cursor-pointer"
                                            title="{{ __('Remove') }}">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 6: PAYMENT -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'payment'" class="space-y-5">
                    <div class="pb-4 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('Payment Methods & Options') }}</h2>
                        <p class="text-xs text-slate-400">{{ __('Review accepted payment gateways, saved cards, and transaction history.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <template x-for="method in paymentMethods" :key="method.id">
                            <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center gap-3">
                                <span class="text-2xl" x-text="getMethodIcon(method.id)"></span>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-extrabold text-xs text-slate-900 dark:text-white" x-text="method.name"></h4>
                                    <p class="text-[10px] text-slate-400 truncate" x-text="method.instructions || '{{ __('Available at checkout') }}'"></p>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('Active') }}
                                </span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TAB 7: CHANGE PASSWORD -->
                <!-- ============================================================ -->
                <div x-show="accountTab === 'password'" class="space-y-5">
                    <div class="pb-4 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ __('Change Password') }}</h2>
                        <p class="text-xs text-slate-400">{{ __('Protect your account with a secure, unique password.') }}</p>
                    </div>

                    <template x-if="passwordMessage">
                        <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-bold" x-text="passwordMessage"></div>
                    </template>
                    <template x-if="passwordError">
                        <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-xs font-bold" x-text="passwordError"></div>
                    </template>

                    <form x-on:submit.prevent="submitPasswordChange()" class="space-y-4 max-w-md">
                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Current Password') }}</label>
                            <input type="password"
                                   x-model="passwordForm.current_password"
                                   required
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-3.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('New Password') }}</label>
                            <input type="password"
                                   x-model="passwordForm.new_password"
                                   required
                                   minlength="6"
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-3.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ __('Confirm New Password') }}</label>
                            <input type="password"
                                   x-model="passwordForm.confirm_password"
                                   required
                                   minlength="6"
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-3.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        </div>

                        <button type="submit"
                                class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition cursor-pointer">
                            {{ __('Update Password') }}
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </template>

    <!-- Interactive Product Review Modal -->
    <div x-show="reviewModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs" x-on:click="closeReviewModal()"></div>

        <div class="relative bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl space-y-4 border border-slate-200/80 dark:border-slate-800"
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">⭐</span>
                    <div>
                        <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ __('Rate & Review Product') }}</h3>
                        <p class="text-[11px] text-slate-400" x-text="selectedProductForReview ? selectedProductForReview.name : ''"></p>
                    </div>
                </div>
                <button type="button" x-on:click="closeReviewModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold p-1">&times;</button>
            </div>

            <template x-if="reviewError">
                <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-bold" x-text="reviewError"></div>
            </template>

            <!-- Star Selection -->
            <div class="space-y-1.5 text-center py-2">
                <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('Your Rating') }}</label>
                <div class="flex items-center justify-center gap-2 text-3xl cursor-pointer select-none">
                    <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                        <button type="button"
                                x-on:click="reviewRating = star"
                                class="transition transform hover:scale-125 focus:outline-hidden"
                                :class="reviewRating >= star ? 'text-amber-400 drop-shadow-xs' : 'text-slate-200 dark:text-slate-700'">
                            ★
                        </button>
                    </template>
                </div>
                <p class="text-xs font-bold text-amber-600 dark:text-amber-400"
                   x-text="{1: '1 Star — Poor', 2: '2 Stars — Fair', 3: '3 Stars — Good', 4: '4 Stars — Very Good', 5: '5 Stars — Excellent!'}[reviewRating] || ''"></p>
            </div>

            <!-- Review Title -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Review Headline (Optional)') }}</label>
                <input type="text"
                       x-model="reviewTitle"
                       placeholder="{{ __('e.g. Excellent build quality and fast shipping') }}"
                       class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-2.5 px-3.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500">
            </div>

            <!-- Review Feedback -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Your Detailed Feedback *') }}</label>
                <textarea rows="3"
                          x-model="reviewComment"
                          placeholder="{{ __('What did you like or dislike about this product? How did it meet your expectations?') }}"
                          class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-2.5 px-3.5 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button"
                        x-on:click="closeReviewModal()"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-extrabold text-xs transition">
                    {{ __('Cancel') }}
                </button>
                <button type="button"
                        x-on:click="submitReview()"
                        :disabled="isSubmittingReview"
                        class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
                    <template x-if="isSubmittingReview">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    </template>
                    <span>{{ __('Submit Review') }}</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
