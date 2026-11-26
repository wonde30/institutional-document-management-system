export interface LandingSummary {
  published_documents: number
  active_offices: number
  correspondence_routed: number
  accreditation_evidence_items: number
  metrics_updated_at: string
}

export interface LandingModule {
  key: string
  title: string
  description: string
  icon: string
  route: string
  order: number
  highlights?: string[]
}

export interface LandingAccreditationStandard {
  code: string
  raw_code: string
  title: string
  evidence_count: number
  status: 'verified' | 'review_pending' | 'in_review' | 'pending'
  status_label: string
}

export interface LandingAccreditationSummary {
  total_standards: number
  verified_standards: number
  in_review_standards: number
  total_evidence_items: number
  coverage_percentage: number
}

export interface LandingAccreditation {
  framework: {
    name: string
    code: string
  }
  cycle: {
    name: string
    starts_on: string
    ends_on: string
  }
  summary: LandingAccreditationSummary
  standards: LandingAccreditationStandard[]
}

export interface LandingAnnouncement {
  id: string
  title: string
  summary: string
  category: string
  published_at: string
  badge: string
  pinned: boolean
}

export interface LegalSection {
  heading: string
  content: string
}

export interface LegalDocument {
  type: 'privacy' | 'terms'
  title: string
  subtitle: string
  effective_date: string
  last_updated: string
  statutory_basis: string
  sections: LegalSection[]
}
