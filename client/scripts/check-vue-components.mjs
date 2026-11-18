import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const baseDir = path.resolve(__dirname, '..');

// Known component libraries
const lucideIcons = ['Search', 'Plus', 'RefreshCw', 'User', 'Users', 'Settings', 'Bell', 'ChevronDown', 'ChevronRight', 'ChevronUp', 'ChevronLeft', 'X', 'Check', 'AlertCircle', 'AlertTriangle', 'Info', 'CheckCircle', 'CheckCircle2', 'XCircle', 'Clock', 'Calendar', 'Filter', 'Download', 'Upload', 'Edit', 'Edit2', 'Edit3', 'Trash', 'Trash2', 'Eye', 'EyeOff', 'Lock', 'Unlock', 'Mail', 'Send', 'Inbox', 'Archive', 'File', 'FileText', 'Folder', 'FolderOpen', 'Image', 'Link', 'ExternalLink', 'ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Building', 'Building2', 'Home', 'Menu', 'MoreVertical', 'MoreHorizontal', 'Star', 'Heart', 'Share', 'Copy', 'Save', 'Printer', 'Phone', 'MapPin', 'Globe', 'Shield', 'ShieldAlert', 'ShieldCheck', 'Award', 'Bookmark', 'Tag', 'Hash', 'Layers', 'Grid', 'List', 'Table', 'Columns', 'Layout', 'Sidebar', 'PanelLeft', 'PanelRight', 'Maximize', 'Minimize', 'ZoomIn', 'ZoomOut', 'RotateCw', 'RotateCcw', 'Repeat', 'Shuffle', 'SkipBack', 'SkipForward', 'Play', 'Pause', 'Square', 'Circle', 'Triangle', 'Hexagon', 'Octagon', 'Diamond', 'Package', 'Box', 'Briefcase', 'ShoppingCart', 'ShoppingBag', 'CreditCard', 'DollarSign', 'Percent', 'TrendingUp', 'TrendingDown', 'BarChart', 'BarChart2', 'BarChart3', 'BarChart4', 'PieChart', 'Activity', 'GitBranch', 'GitCommit', 'GitMerge', 'GitPullRequest', 'Github', 'Gitlab', 'Code', 'Code2', 'Terminal', 'Command', 'Database', 'Server', 'HardDrive', 'Cpu', 'Smartphone', 'Tablet', 'Laptop', 'Monitor', 'Tv', 'Watch', 'Camera', 'Video', 'Mic', 'MicOff', 'Volume', 'Volume1', 'Volume2', 'VolumeX', 'Wifi', 'WifiOff', 'Bluetooth', 'Cast', 'Radio', 'Signal', 'Zap', 'Battery', 'BatteryCharging', 'BatteryFull', 'BatteryLow', 'BatteryMedium', 'Power', 'PowerOff', 'Plug', 'Anchor', 'Aperture', 'Compass', 'Target', 'Navigation', 'Navigation2', 'Move', 'Crosshair', 'MousePointer', 'MousePointer2', 'Hand', 'Fingerprint', 'Key', 'KeyRound', 'Scissors', 'Paperclip', 'Link2', 'Unlink', 'Unlink2', 'Feather', 'PenTool', 'Pen', 'PenLine', 'Paintbrush', 'Paintbrush2', 'Palette', 'Pipette', 'Droplet', 'Droplets', 'Umbrella', 'Cloud', 'CloudRain', 'CloudSnow', 'CloudLightning', 'Sun', 'Moon', 'Stars', 'Sunrise', 'Sunset', 'Wind', 'Thermometer', 'ThermometerSun', 'ThermometerSnowflake', 'Flame', 'Snowflake', 'Waves', 'Leaf', 'TreeDeciduous', 'TreePine', 'Flower', 'Flower2', 'Sprout', 'Bird', 'Bug', 'Cat', 'Dog', 'Fish', 'Rabbit', 'Squirrel', 'Turtle', 'Apple', 'Banana', 'Cherry', 'Grape', 'Lemon', 'Orange', 'Carrot', 'Pizza', 'Coffee', 'Beer', 'Wine', 'Utensils', 'UtensilsCrossed', 'Soup', 'Sandwich', 'Milk', 'Egg', 'Cookie', 'IceCream', 'Cake', 'Candy', 'Popcorn', 'Salad', 'Drumstick', 'Bone', 'Pill', 'Syringe', 'Stethoscope', 'Bandage', 'Cigarette', 'CigaretteOff', 'Dumbbell', 'Bike', 'Car', 'Bus', 'Truck', 'Plane', 'PlaneTakeoff', 'PlaneLanding', 'Train', 'Ship', 'Rocket', 'Fuel', 'Construction', 'Hammer', 'Wrench', 'Screwdriver', 'Ruler', 'Gauge', 'PackageCheck', 'PackageMinus', 'PackagePlus', 'PackageSearch', 'PackageX', 'Stamp', 'Wallet', 'Coins', 'Banknote', 'Receipt', 'Ticket', 'Gift', 'PartyPopper', 'Lightbulb', 'LightbulbOff', 'Lamp', 'Fan', 'Heater', 'AirVent', 'Projector', 'FlashlightOn', 'FlashlightOff', 'Flashlight', 'Medal', 'Trophy', 'Crown', 'Swords', 'Sword', 'Axe', 'Wand', 'Wand2', 'Sparkles', 'Sparkle', 'Ghost', 'Skull', 'BugOff', 'Microscope', 'Telescope', 'Atom', 'Dna', 'Flask', 'FlaskConical', 'FlaskRound', 'TestTube', 'TestTube2', 'Scan', 'ScanLine', 'ScanFace', 'ScanEye', 'BookOpen', 'BookMarked', 'Book', 'Library', 'Newspaper', 'Scroll', 'ScrollText', 'FileEdit', 'FilePlus', 'FileMinus', 'FileCheck', 'FileCheck2', 'FileX', 'FileClock', 'FileArchive', 'FileCode', 'FileCode2', 'FileJson', 'FileJson2', 'FileSpreadsheet', 'FileBarChart', 'FileBarChart2', 'FileLineChart', 'FilePieChart', 'FileInput', 'FileOutput', 'FileSearch', 'FileSearch2', 'FileType', 'FileType2', 'FileWarning', 'FileQuestion', 'FileLock', 'FileLock2', 'FileKey', 'FileKey2', 'FileHeart', 'FileScan', 'FileStack', 'Files', 'FolderTree', 'FolderKanban', 'FolderGit', 'FolderGit2', 'FolderCheck', 'FolderX', 'FolderPlus', 'FolderMinus', 'FolderSearch', 'FolderSearch2', 'FolderEdit', 'FolderClock', 'FolderArchive', 'FolderInput', 'FolderOutput', 'FolderSymlink', 'FolderSync', 'FolderLock', 'FolderKey', 'FolderHeart', 'ClipboardCheck', 'ClipboardCopy', 'ClipboardEdit', 'ClipboardList', 'ClipboardPaste', 'ClipboardSignature', 'ClipboardType', 'ClipboardX', 'Clipboard', 'CalendarCheck', 'CalendarCheck2', 'CalendarClock', 'CalendarDays', 'CalendarHeart', 'CalendarMinus', 'CalendarOff', 'CalendarPlus', 'CalendarRange', 'CalendarSearch', 'CalendarX', 'CalendarX2', 'Hourglass', 'Timer', 'TimerOff', 'TimerReset', 'Alarm', 'AlarmCheck', 'AlarmClock', 'AlarmClockOff', 'AlarmMinus', 'AlarmPlus', 'Scale', 'Network'];

function getAllVueFiles(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  
  list.forEach((file) => {
    const filePath = path.join(dir, file);
    const stat = fs.statSync(filePath);
    
    if (stat && stat.isDirectory()) {
      if (!file.startsWith('.') && file !== 'node_modules') {
        results = results.concat(getAllVueFiles(filePath));
      }
    } else if (file.endsWith('.vue')) {
      results.push(filePath);
    }
  });
  
  return results;
}

function extractImports(scriptContent) {
  const imports = new Set();
  
  // Match import statements - handle both default and named imports together
  // Pattern: import Default, { Named1, Named2 } from 'module'
  const importRegex = /import\s+(?:(\w+)(?:\s*,\s*)?)?(?:{([^}]+)})?\s+from\s+['"]([^'"]+)['"]/g;
  let match;
  
  while ((match = importRegex.exec(scriptContent)) !== null) {
    // Default import
    if (match[1]) {
      imports.add(match[1]);
    }
    
    // Named imports
    if (match[2]) {
      match[2].split(',').forEach((name) => {
        const trimmed = name.trim();
        // Remove 'type' keyword if present
        const withoutType = trimmed.replace(/^type\s+/, '');
        
        // Handle 'X as Y' - we want the alias Y
        const asMatch = withoutType.match(/(\w+)\s+as\s+(\w+)/);
        if (asMatch) {
          imports.add(asMatch[2]); // Add the alias
        } else if (withoutType) {
          imports.add(withoutType);
        }
      });
    }
  }
  
  return imports;
}

function extractTemplateComponents(templateContent) {
  const components = new Set();
  
  // Match PascalCase component tags
  const componentRegex = /<([A-Z][a-zA-Z0-9]*)/g;
  let match;
  
  while ((match = componentRegex.exec(templateContent)) !== null) {
    const comp = match[1];
    // Skip built-in Vue components
    if (!['Transition', 'TransitionGroup', 'KeepAlive', 'Teleport', 'Suspense'].includes(comp)) {
      components.add(comp);
    }
  }
  
  return components;
}

function checkDuplicateImports(scriptContent) {
  const imports = [];
  const importRegex = /import\s+(?:{([^}]+)}|(\w+))\s+from/g;
  let match;
  
  while ((match = importRegex.exec(scriptContent)) !== null) {
    if (match[1]) {
      match[1].split(',').forEach((name) => {
        const trimmed = name.trim();
        // Remove 'type' keyword if present
        const withoutType = trimmed.replace(/^type\s+/, '');
        
        // Handle 'X as Y' - we want the alias Y
        const asMatch = withoutType.match(/(\w+)\s+as\s+(\w+)/);
        if (asMatch) {
          imports.push(asMatch[2]); // Add the alias
        } else {
          imports.push(withoutType);
        }
      });
    } else if (match[2]) {
      imports.push(match[2]);
    }
  }
  
  const duplicates = imports.filter((item, index) => imports.indexOf(item) !== index);
  return [...new Set(duplicates)];
}

function checkTrailingCommas(scriptContent) {
  const issues = [];
  
  // Check for ,} or ,] in import statements
  const trailingCommaPattern = /import\s+{[^}]*,\s*}\s+from/g;
  if (trailingCommaPattern.test(scriptContent)) {
    issues.push('Trailing comma in import');
  }
  
  return issues;
}

function checkInvalidSlots(templateContent) {
  const issues = [];
  
  // Check for <template #subtitle> which PageHeader doesn't support
  if (templateContent.includes('<template #subtitle>')) {
    issues.push('<template #subtitle> - PageHeader uses subtitle prop, not slot');
  }
  
  // Check for other undefined slots on PageHeader - look for PageHeader blocks
  const pageHeaderRegex = /<PageHeader[^>]*>([\s\S]*?)<\/PageHeader>/g;
  let match;
  
  while ((match = pageHeaderRegex.exec(templateContent)) !== null) {
    const pageHeaderContent = match[1];
    const slotRegex = /<template\s+#(\w+)>/g;
    let slotMatch;
    
    while ((slotMatch = slotRegex.exec(pageHeaderContent)) !== null) {
      const slotName = slotMatch[1];
      if (!['breadcrumb', 'actions'].includes(slotName)) {
        issues.push(`<template #${slotName}> - PageHeader only supports breadcrumb and actions slots`);
      }
    }
  }
  
  return issues;
}

console.log('VUE COMPONENT INTEGRITY CHECK');
console.log('=============================\n');

const srcDir = path.join(baseDir, 'src');
const vueFiles = getAllVueFiles(srcDir);

let totalIssues = 0;
const issuesByCategory = {
  unresolved: 0,
  duplicate: 0,
  trailingComma: 0,
  invalidSlot: 0,
};

vueFiles.forEach((filePath) => {
  const content = fs.readFileSync(filePath, 'utf8');
  const relativePath = path.relative(baseDir, filePath).replace(/\\/g, '/');
  
  // Split into script and template
  const scriptMatch = content.match(/<script[^>]*>([\s\S]*?)<\/script>/);
  const templateMatch = content.match(/<template>([\s\S]*?)<\/template>/);
  
  if (!scriptMatch || !templateMatch) return;
  
  const scriptContent = scriptMatch[1];
  const templateContent = templateMatch[1];
  
  const fileIssues = [];
  
  // 1. Check unresolved components
  const imports = extractImports(scriptContent);
  const usedComponents = extractTemplateComponents(templateContent);
  
  usedComponents.forEach((component) => {
    if (!imports.has(component) && !lucideIcons.includes(component)) {
      fileIssues.push(`  ✗ Unresolved: ${component}`);
      issuesByCategory.unresolved++;
    }
  });
  
  // 2. Check duplicate imports
  const duplicates = checkDuplicateImports(scriptContent);
  duplicates.forEach((dup) => {
    fileIssues.push(`  ✗ Duplicate import: ${dup}`);
    issuesByCategory.duplicate++;
  });
  
  // 3. Check trailing commas
  const trailingIssues = checkTrailingCommas(scriptContent);
  trailingIssues.forEach((issue) => {
    fileIssues.push(`  ✗ ${issue}`);
    issuesByCategory.trailingComma++;
  });
  
  // 4. Check invalid slots
  const slotIssues = checkInvalidSlots(templateContent);
  slotIssues.forEach((issue) => {
    fileIssues.push(`  ✗ Invalid slot: ${issue}`);
    issuesByCategory.invalidSlot++;
  });
  
  if (fileIssues.length > 0) {
    console.log(`${relativePath}:`);
    fileIssues.forEach((issue) => console.log(issue));
    console.log('');
    totalIssues += fileIssues.length;
  }
});

console.log('\nSUMMARY');
console.log('-------');
console.log(`Unresolved components: ${issuesByCategory.unresolved}`);
console.log(`Duplicate imports: ${issuesByCategory.duplicate}`);
console.log(`Trailing commas: ${issuesByCategory.trailingComma}`);
console.log(`Invalid slots: ${issuesByCategory.invalidSlot}`);
console.log(`\nTotal issues: ${totalIssues}`);

if (totalIssues === 0) {
  console.log('\n✓ All Vue components are properly configured');
}

process.exit(totalIssues > 0 ? 1 : 0);
