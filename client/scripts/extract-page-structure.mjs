#!/usr/bin/env node
/**
 * Extract actual page structure from templates
 */

import { readdirSync, readFileSync, statSync } from 'fs'
import { join } from 'path'

const viewsDir = 'client/src/views'

function findInTemplate(content, pattern, defaultValue = '') {
  const match = content.match(pattern)
  return match ? match[1].trim() : defaultValue
}

function extractPageInfo(file, content) {
  // Find eyebrow (usually .notion-eyebrow or text-xs...text-primary)
  const eyebrowPatterns = [
    /<[^>]*class="[^"]*notion-eyebrow[^"]*"[^>]*>([^<]+)/,
    /<[^>]*class="[^"]*text-xs[^"]*font-bold[^"]*text-primary[^"]*"[^>]*>([^<]+)/,
    /<div[^>]*class="[^"]*text-xs[^"]*text-primary[^"]*"[^>]*>([^<]+)/
  ]
  
  let eyebrow = ''
  for (const pattern of eyebrowPatterns) {
    eyebrow = findInTemplate(content, pattern)
    if (eyebrow) break
  }
  
  // Find h1
  const h1 = findInTemplate(content, /<h1[^>]*>([^<]+)/, '[No H1]')
  
  // Find subtitle (usually text-sm text-ink-muted after h1)
  const subtitle = findInTemplate(content, /<p[^>]*class="[^"]*text-sm[^"]*text-ink-muted[^"]*"[^>]*>([^<]+)/)
  
  // Find route from router (file-based guess)
  const routeMap = {
    'LandingPageView': '/',
    'LoginView': '/login',
    'ForgotPasswordView': '/forgot-password',
    'DashboardView': '/dashboard',
    'DocumentListView': '/documents',
    'DocumentCreateView': '/documents/create',
    'DocumentDetailView': '/documents/:id',
    'CorrespondenceRegistryView': '/correspondence',
    'ArchiveListView': '/archive',
    'WorkflowTasksView': '/workflows',
    'OperationsView': '/operations',
    'ReportsView': '/reports',
    'UserManagementView': '/admin/users',
    'RoleManagementView': '/admin/roles',
    'PermissionManagementView': '/admin/permissions',
    'OrganizationUnitsView': '/admin/org-units',
    'ReferenceDataView': '/admin/reference',
    'SystemSettingsView': '/admin/settings',
    'AuditLogView': '/admin/audit',
    'InstitutionalConfigurationCenterView': '/config',
    'TemplateBuilderView': '/templates',
    'OfficeProceduresView': '/procedures',
    'SignaturesStampsView': '/signatures',
    'AccreditationEvidenceView': '/accreditation',
    'FinalizedResearchView': '/research',
    'ProjectsInnovationView': '/projects',
    'StaffRecordsView': '/staff-records',
    'DataImportsExportsView': '/imports',
    'GlobalSearchView': '/search',
    'NotificationsView': '/notifications',
    'AccountProfileView': '/account',
    'HelpGuideView': '/help',
    'TermsConditionsView': '/terms',
    'PrivacyPolicyView': '/privacy'
  }
  
  const viewName = file.replace(/\.vue$/, '')
  const route = routeMap[viewName] || `/${viewName.replace('View', '').toLowerCase()}`
  
  // Breadcrumb (derived from route or h1)
  const breadcrumb = h1.replace(/^(Sign in to your account|Workspace|Create New Document)$/, 
    (match) => {
      if (match === 'Sign in to your account') return 'Login'
      if (match === 'Workspace') return 'Dashboard'
      if (match === 'Create New Document') return 'Create Document'
      return match
    })
  
  return { route, breadcrumb, eyebrow, h1, subtitle, file }
}

function scanViews(dir, results = []) {
  const entries = readdirSync(dir)
  
  for (const entry of entries) {
    const fullPath = join(dir, entry)
    const stat = statSync(fullPath)
    
    if (stat.isDirectory()) {
      scanViews(fullPath, results)
    } else if (entry.endsWith('View.vue')) {
      try {
        const content = readFileSync(fullPath, 'utf8')
        const info = extractPageInfo(entry, content)
        if (info.h1 !== '[No H1]') {
          results.push(info)
        }
      } catch (err) {
        // skip
      }
    }
  }
  
  return results
}

const pages = scanViews(viewsDir)
pages.sort((a, b) => a.route.localeCompare(b.route))

console.log('RULE 6: TOP-LEVEL PAGES (FROM ACTUAL TEMPLATES)')
console.log('=================================================\n')
console.log('Route                           | Breadcrumb              | Eyebrow                    | H1                                      | Subtitle')
console.log('--------------------------------|-------------------------|----------------------------|-----------------------------------------|------------------------------------------')

for (const p of pages) {
  const route = p.route.padEnd(31)
  const breadcrumb = p.breadcrumb.substring(0, 23).padEnd(23)
  const eyebrow = p.eyebrow.substring(0, 26).padEnd(26)
  const h1 = p.h1.substring(0, 39).padEnd(39)
  const subtitle = p.subtitle.substring(0, 41)
  
  console.log(`${route} | ${breadcrumb} | ${eyebrow} | ${h1} | ${subtitle}`)
}

console.log(`\nTotal: ${pages.length} pages`)
