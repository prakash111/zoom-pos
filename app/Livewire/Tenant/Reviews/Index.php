<?php

namespace App\Livewire\Tenant\Reviews;

use App\Models\Company;
use App\Models\ProductReview;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Product Ratings & Reviews'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all, approved, pending
    public string $ratingFilter = 'all'; // all, 5, 4, 3, 2, 1

    public bool $enable_product_reviews = true;
    public bool $require_review_approval = false;

    public ?string $successMessage = null;

    protected function getCompanyId(): string
    {
        $id = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth()->user()?->company_id;
        return (string) ($id ?? Company::first()?->id ?? '');
    }

    public function mount(): void
    {
        $company = Company::withoutGlobalScopes()->find($this->getCompanyId());
        if ($company) {
            $this->enable_product_reviews = (bool) ($company->enable_product_reviews ?? true);
            $this->require_review_approval = (bool) ($company->require_review_approval ?? false);
        }
    }

    public function toggleReviewsEnabled(): void
    {
        $this->enable_product_reviews = ! $this->enable_product_reviews;
        Company::withoutGlobalScopes()->where('id', $this->getCompanyId())->update([
            'enable_product_reviews' => $this->enable_product_reviews,
        ]);
        $this->successMessage = $this->enable_product_reviews
            ? 'Product ratings and customer reviews enabled across your storefront!'
            : 'Product ratings and reviews disabled across your storefront.';
    }

    public function toggleReviewApproval(): void
    {
        $this->require_review_approval = ! $this->require_review_approval;
        Company::withoutGlobalScopes()->where('id', $this->getCompanyId())->update([
            'require_review_approval' => $this->require_review_approval,
        ]);
        $this->successMessage = $this->require_review_approval
            ? 'New reviews will now require store admin approval before appearing.'
            : 'New reviews will now appear instantly without prior moderation.';
    }

    public function approve(int $id): void
    {
        $review = ProductReview::where('company_id', $this->getCompanyId())->findOrFail($id);
        $review->update(['is_approved' => true]);
        $this->successMessage = "Review from {$review->customer_name} approved and published!";
    }

    public function reject(int $id): void
    {
        $review = ProductReview::where('company_id', $this->getCompanyId())->findOrFail($id);
        $review->update(['is_approved' => false]);
        $this->successMessage = "Review from {$review->customer_name} hidden from store.";
    }

    public function delete(int $id): void
    {
        $review = ProductReview::where('company_id', $this->getCompanyId())->findOrFail($id);
        $review->delete();
        $this->successMessage = 'Review permanently deleted.';
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $query = ProductReview::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with('product');

        if (! empty($this->search)) {
            $s = '%'.$this->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('customer_name', 'like', $s)
                  ->orWhere('title', 'like', $s)
                  ->orWhere('comment', 'like', $s)
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', $s));
            });
        }

        if ($this->statusFilter === 'approved') {
            $query->where('is_approved', true);
        } elseif ($this->statusFilter === 'pending') {
            $query->where('is_approved', false);
        }

        if ($this->ratingFilter !== 'all') {
            $query->where('rating', (int) $this->ratingFilter);
        }

        $reviews = $query->orderByDesc('created_at')->paginate(15);

        $totalReviews = ProductReview::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $pendingReviews = ProductReview::withoutGlobalScopes()->where('company_id', $companyId)->where('is_approved', false)->count();
        $avgRating = $totalReviews > 0
            ? round((float) ProductReview::withoutGlobalScopes()->where('company_id', $companyId)->where('is_approved', true)->avg('rating'), 1)
            : 5.0;

        return view('livewire.tenant.reviews.index', [
            'reviews' => $reviews,
            'totalReviews' => $totalReviews,
            'pendingReviews' => $pendingReviews,
            'avgRating' => $avgRating,
        ]);
    }
}
