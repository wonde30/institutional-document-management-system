import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const baseDir = path.resolve(__dirname, '..');

// Known component libraries
const lucideIcons = ['Search', 'Plus', 'RefreshCw', 'User', 'Users', 'Settings', 'Bell', 'ChevronDown', 'ChevronRight', 'ChevronUp', 'ChevronLeft', 'X', 'Check', 'AlertCircle', 'AlertTriangle', 'Info', 'CheckCircle', 'CheckCircle2', 'XCircle', 'Clock', 'Calendar', 'Filter', 'Download', 'Upload', 'Edit', 'Edit2', 'Edit3', 'Trash', 'Trash2', 'Eye', 'EyeOff', 'Lock', 'Unlock', 'Mail', 'Send', 'Inbox', 'Archive', 'File', 'FileText', 'Folder', 'FolderOpen', 'Image', 'Link', 'ExternalLink', 'ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Building', 'Building2', 'Home', 'Menu', 'MoreVertical', 'MoreHorizontal', 'Star', 'Heart', 'Share', 'Copy', 'Save', 'Printer', 'Phone', 'MapPin', 'Globe', 'Shield', 'ShieldAlert', 'ShieldCheck', 'Award', 'Bookmark', 'Tag', 'Hash', 'Layers', 'Grid', 'List', 'Table', 'Columns', 'Layout', 'Sidebar', 'PanelLeft', 'PanelRight', 'Maximize', 'Minimize', 'ZoomIn', 'ZoomOut', 'RotateCw', 'RotateCcw', 'Repeat', 'Shuffle', 'SkipBack', 'SkipForward', 'Play', 'Pause', 'Square', 'Circle', 'Triangle', 'Hexagon', 'Octagon', 'Diamond', 'Package', 'Box', 'Briefcase', 'ShoppingCart', 'ShoppingBag', 'CreditCard', 'DollarSign', 'Percent', 'TrendingUp', 'TrendingDown', 'BarChart', 'BarChart2', 'BarChart3', 'BarChart4', 'PieChart', 'Activity', 'GitBranch', 'GitCommit', 'GitMerge', 'GitPullRequest', 'Github', 'Gitlab', 'Code', 'Code2', 'Terminal', 'Command', 'Database', 'Server', 'HardDrive', 'Cpu', 'Smartphone', 'Tablet', 'Laptop', 'Monitor', 'Tv', 'Watch', 'Camera', 'Video', 'Mic', 'MicOff', 'Volume', 'Volume1', 'Volume2', 'VolumeX', 'Wifi', 'WifiOff', 'Bluetooth', 'Cast', 'Radio', 'Signal', 'Zap', 'Battery', 'BatteryCharging', 'BatteryFull', 'BatteryLow', 'BatteryMedium', 'Power', 'PowerOff', 'Plug', 'Anchor', 'Aperture', 'Compass', 'Target', 'Navigation', 'Navigation2', 'Move', 'Crosshair', 'MousePointer', 'MousePointer2', 'Hand', 'Fingerprint', 'Key', 'KeyRound', 'Unlock', 'Scissors', 'Paperclip', 'Link2', 'Unlink', 'Unlink2', 'Feather', 'PenTool', 'Pen', 'PenLine', 'Paintbrush', 'Paintbrush2', 'Palette', 'Pipette', 'Droplet', 'Droplets', 'Umbrella', 'Cloud', 'CloudRain', 'CloudSnow', 'CloudLightning', 'Sun', 'Moon', 'Stars', 'Sunrise', 'Sunset', 'Wind', 'Thermometer', 'ThermometerSun', 'ThermometerSnowflake', 'Flame', 'Snowflake', 'Waves', 'Leaf', 'TreeDeciduous', 'TreePine', 'Flower', 'Flower2', 'Sprout', 'Bird', 'Bug', 'Cat', 'Dog', 'Fish', 'Rabbit', 'Squirrel', 'Turtle', 'Apple', 'Banana', 'Cherry', 'Grape', 'Lemon', 'Orange', 'Carrot', 'Pizza', 'Coffee', 'Beer', 'Wine', 'Utensils', 'UtensilsCrossed', 'Soup', 'Sandwich', 'Milk', 'Egg', 'Cookie', 'IceCream', 'Cake', 'Candy', 'Popcorn', 'Salad', 'Drumstick', 'Bone', 'Pill', 'Syringe', 'Stethoscope', 'Bandage', 'Cigarette', 'CigaretteOff', 'Dumbbell', 'Bike', 'Car', 'Bus', 'Truck', 'Plane', 'PlaneTakeoff', 'PlaneLanding', 'Train', 'Ship', 'Rocket', 'Fuel', 'Construction', 'Hammer', 'Wrench', 'Screwdriver', 'Ruler', 'Gauge', 'PackageCheck', 'PackageMinus', 'PackagePlus', 'PackageSearch', 'PackageX', 'Stamp', 'Wallet', 'Coins', 'Banknote', 'Receipt', 'Ticket', 'Gift', 'PartyPopper', 'Lightbulb', 'LightbulbOff', 'Lamp', 'Fan', 'Heater', 'AirVent', 'Projector', 'FlashlightOn', 'FlashlightOff', 'Flashlight', 'Medal', 'Trophy', 'Crown', 'Swords', 'Sword', 'Axe', 'Wand', 'Wand2', 'Sparkles', 'Sparkle', 'Ghost', 'Skull', 'Bug', 'BugOff', 'Microscope', 'Telescope', 'Atom', 'Dna', 'Flask', 'FlaskConical', 'FlaskRound', 'TestTube', 'TestTube2', 'Scan', 'ScanLine', 'ScanFace', 'ScanEye', 'Fingerprint', 'BookOpen', 'BookMarked', 'Book', 'Library', 'Newspaper', 'Scroll', 'ScrollText', 'FileEdit', 'FilePlus', 'FileMinus', 'FileCheck', 'FileX', 'FileClock', 'FileArchive', 'FileCode', 'FileCode2', 'FileJson', 'FileJson2', 'FileSpreadsheet', 'FileBarChart', 'FileBarChart2', 'FileLineChart', 'FilePieChart', 'FileInput', 'FileOutput', 'FileSearch', 'FileSearch2', 'FileType', 'FileType2', 'FileWarning', 'FileQuestion', 'FileLock', 'FileLock2', 'FileKey', 'FileKey2', 'FileHeart', 'FileScan', 'FileStack', 'Files', 'FolderTree', 'FolderKanban', 'FolderGit', 'FolderGit2', 'FolderCheck', 'FolderX', 'FolderPlus', 'FolderMinus', 'FolderSearch', 'FolderSearch2', 'FolderEdit', 'FolderClock', 'FolderArchive', 'FolderInput', 'FolderOutput', 'FolderSymlink', 'FolderSync', 'FolderLock', 'FolderKey', 'FolderHeart', 'ClipboardCheck', 'ClipboardCopy', 'ClipboardEdit', 'ClipboardList', 'ClipboardPaste', 'ClipboardSignature', 'ClipboardType', 'ClipboardX', 'Clipboard', 'Calendar', 'CalendarCheck', 'CalendarCheck2', 'CalendarClock', 'CalendarDays', 'CalendarHeart', 'CalendarMinus', 'CalendarOff', 'CalendarPlus', 'CalendarRange', 'CalendarSearch', 'CalendarX', 'CalendarX2', 'Hourglass', 'Timer', 'TimerOff', 'TimerReset', 'Alarm', 'AlarmCheck', 'AlarmClock', 'AlarmClockOff', 'AlarmMinus', 'AlarmPlus'];

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
  
  // Match import statements
  const importRegex = /import\s+(?:{([^}]+)}|(\w+))\s+from\s+['"]([^'"]+)['"]/g;
  let match;
  
  while ((match = importRegex.exec(scriptContent)) !== null) {
    if (match[1]) {
      // Named imports
      match[1].split(',').forEach((name) => {
        const cleaned = name.trim().replace(/\s+as\s+\w+$/, '');
        imports.add(cleaned);
      });
    } else if (match[2]) {
      // Default import
      imports.add(match[2]);
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
    components.add(match[1]);
  }
  
  return components;
}

console.log('UNRESOLVED COMPONENT CHECK');
console.log('==========================\n');

const viewsDir = path.join(baseDir, 'src', 'views');
const vueFiles = getAllVueFiles(viewsDir);

let totalIssues = 0;

vueFiles.forEach((filePath) => {
  const content = fs.readFileSync(filePath, 'utf8');
  const relativePath = path.relative(baseDir, filePath).replace(/\\/g, '/');
  
  // Split into script and template
  const scriptMatch = content.match(/<script[^>]*>([\s\S]*?)<\/script>/);
  const templateMatch = content.match(/<template>([\s\S]*?)<\/template>/);
  
  if (!scriptMatch || !templateMatch) return;
  
  const scriptContent = scriptMatch[1];
  const templateContent = templateMatch[1];
  
  const imports = extractImports(scriptContent);
  const usedComponents = extractTemplateComponents(templateContent);
  
  const unresolved = [];
  
  usedComponents.forEach((component) => {
    // Check if it's imported
    if (!imports.has(component)) {
      // Check if it's a known lucide icon
      if (lucideIcons.includes(component)) {
        unresolved.push(`${component} (lucide icon not imported)`);
      } else {
        unresolved.push(component);
      }
    }
  });
  
  if (unresolved.length > 0) {
    console.log(`${relativePath}:`);
    unresolved.forEach((comp) => {
      console.log(`  ✗ ${comp}`);
    });
    console.log('');
    totalIssues += unresolved.length;
  }
});

console.log(`\nTotal unresolved components: ${totalIssues}`);

if (totalIssues === 0) {
  console.log('✓ All components properly imported');
}

process.exit(totalIssues > 0 ? 1 : 0);
