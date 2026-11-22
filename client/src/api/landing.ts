import { apiClient } from '@/api/client'
import type {
  ApiResponse,
  LandingSummary,
  LandingModule,
  LandingAccreditation,
  LandingAnnouncement,
  LegalDocument,
} from '@/types'

export const landingApi = {
  getSummary: () =>
    apiClient.get<ApiResponse<LandingSummary>>('/public/landing/summary'),

  getModules: () =>
    apiClient.get<ApiResponse<LandingModule[]>>('/public/landing/modules'),

  getAccreditation: () =>
    apiClient.get<ApiResponse<LandingAccreditation>>('/public/landing/accreditation'),

  getAnnouncements: () =>
    apiClient.get<ApiResponse<LandingAnnouncement[]>>('/public/landing/announcements'),

  getLegal: (type: 'privacy' | 'terms') =>
    apiClient.get<ApiResponse<LegalDocument>>(`/public/legal/${type}`),
}
