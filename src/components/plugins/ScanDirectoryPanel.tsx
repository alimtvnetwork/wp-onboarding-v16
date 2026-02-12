 import { useState } from "react";
 import { Button } from "@/components/ui/button";
 import { Input } from "@/components/ui/input";
 import { Label } from "@/components/ui/label";
 import { Badge } from "@/components/ui/badge";
 import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
 import { Checkbox } from "@/components/ui/checkbox";
 import {
   Search,
   Loader2,
   CheckCircle,
   XCircle,
   FileJson,
   Plus,
   FolderSearch,
 } from "lucide-react";
 import { api, Plugin } from "@/lib/api";
 import { toast } from "sonner";
 import { cn } from "@/lib/utils";
 
 interface ScanResult {
   path: string;
   isValid: boolean;
   pluginName?: string;
   version?: string;
   mainFile?: string;
   description?: string;
   author?: string;
   textDomain?: string;
   fileCount: number;
   totalSize: number;
   error?: string;
   detectionCreated?: boolean;
 }
 
 interface ScanDirectoryPanelProps {
   existingPlugins: Plugin[];
   onPluginAdded: () => void;
 }
 
 export function ScanDirectoryPanel({ existingPlugins, onPluginAdded }: ScanDirectoryPanelProps) {
   const [scanPath, setScanPath] = useState("");
   const [isScanning, setIsScanning] = useState(false);
   const [scanResult, setScanResult] = useState<ScanResult | null>(null);
   const [createDetectionFile, setCreateDetectionFile] = useState(true);
   const [isAdding, setIsAdding] = useState(false);
 
   // Check if path is already registered
   const isAlreadyRegistered = existingPlugins.some(
     (p) => p.path.toLowerCase() === scanPath.toLowerCase()
   );
 
   const handleScan = async () => {
     if (!scanPath.trim()) {
       toast.error("Please enter a directory path");
       return;
     }
 
     setIsScanning(true);
     setScanResult(null);
 
     try {
       const response = await api.scanDirectory(scanPath.trim(), createDetectionFile);
       if (response.success && response.data) {
         setScanResult(response.data);
         if (response.data.isValid) {
           if (response.data.detectionCreated) {
             toast.success("Plugin detected! wp-plugin-detected.json created");
           } else {
             toast.success(`Plugin detected: ${response.data.pluginName}`);
           }
         } else {
           toast.warning(response.data.error || "Not a valid WordPress plugin");
         }
       } else {
         toast.error(response.error?.message || "Scan failed");
       }
     } catch (error: unknown) {
       toast.error(error instanceof Error ? error.message : "Failed to scan directory");
     } finally {
       setIsScanning(false);
     }
   };
 
   const handleAddPlugin = async () => {
     if (!scanResult?.isValid || !scanResult.pluginName) return;
 
     if (isAlreadyRegistered) {
       toast.info("This plugin is already registered");
       return;
     }
 
     setIsAdding(true);
     try {
       const response = await api.createPlugin({
         name: scanResult.pluginName,
         path: scanPath.trim(),
       });
 
       if (response.success) {
         toast.success(`Plugin "${scanResult.pluginName}" registered`);
         onPluginAdded();
         setScanPath("");
         setScanResult(null);
       } else if (response.error) {
         // Check for duplicate error
         const msg = response.error.message || "";
         const code = response.error.code || "";
         const isDuplicate =
           code === "E2009" ||
           msg.includes("E2009") ||
           msg.toLowerCase().includes("already registered");
 
         if (isDuplicate) {
           toast.info("Plugin is already registered");
           onPluginAdded(); // Refresh list
         } else {
           toast.error(response.error.message);
         }
       }
     } catch (error: unknown) {
       toast.error(error instanceof Error ? error.message : "Failed to register plugin");
     } finally {
       setIsAdding(false);
     }
   };
 
   const formatFileSize = (bytes: number) => {
     if (bytes < 1024) return `${bytes} B`;
     if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
     return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
   };
 
   return (
     <Card className="border-dashed">
       <CardHeader className="pb-3">
         <CardTitle className="text-base flex items-center gap-2">
           <FolderSearch className="h-4 w-4" />
           Scan Directory
         </CardTitle>
         <CardDescription>
           Scan a local directory to detect WordPress plugins and auto-register them
         </CardDescription>
       </CardHeader>
       <CardContent className="space-y-4">
         <div className="flex gap-2">
           <div className="flex-1">
             <Input
               placeholder="C:\Projects\my-plugin or /home/user/plugins/my-plugin"
               value={scanPath}
               onChange={(e) => {
                 setScanPath(e.target.value);
                 setScanResult(null); // Clear previous result
               }}
               className="font-mono text-sm"
               onKeyDown={(e) => e.key === "Enter" && handleScan()}
             />
           </div>
           <Button onClick={handleScan} disabled={isScanning || !scanPath.trim()}>
             {isScanning ? (
               <Loader2 className="h-4 w-4 animate-spin" />
             ) : (
               <Search className="h-4 w-4" />
             )}
             <span className="ml-2 hidden sm:inline">Scan</span>
           </Button>
         </div>
 
         {/* Create detection file option */}
         <div className="flex items-center gap-2">
           <Checkbox
             id="create-detection"
             checked={createDetectionFile}
             onCheckedChange={(checked) => setCreateDetectionFile(!!checked)}
           />
           <Label htmlFor="create-detection" className="text-sm cursor-pointer">
             Create <code className="text-xs bg-muted px-1 rounded">wp-plugin-detected.json</code> if valid
           </Label>
         </div>
 
         {/* Already registered warning */}
         {scanPath && isAlreadyRegistered && (
           <div className="rounded-lg border border-warning bg-warning/10 p-3">
             <p className="text-sm text-warning font-medium">
               ⚠️ This path is already registered as a plugin
             </p>
           </div>
         )}
 
         {/* Scan result */}
         {scanResult && (
           <div
             className={cn(
               "rounded-lg border p-4 space-y-3",
               scanResult.isValid
                 ? "border-primary/50 bg-primary/5"
                 : "border-destructive/50 bg-destructive/5"
             )}
           >
             <div className="flex items-start justify-between gap-2">
               <div className="flex items-center gap-2">
                 {scanResult.isValid ? (
                   <CheckCircle className="h-5 w-5 text-primary" />
                 ) : (
                   <XCircle className="h-5 w-5 text-destructive" />
                 )}
                 <span className="font-medium">
                   {scanResult.isValid ? "Valid WordPress Plugin" : "Invalid Plugin"}
                 </span>
               </div>
               {scanResult.detectionCreated && (
                 <Badge variant="secondary" className="text-xs">
                   <FileJson className="h-3 w-3 mr-1" />
                   JSON Created
                 </Badge>
               )}
             </div>
 
             {scanResult.isValid ? (
               <>
                 <div className="grid grid-cols-2 gap-2 text-sm">
                   <div>
                     <span className="text-muted-foreground">Name:</span>{" "}
                     <span className="font-medium">{scanResult.pluginName}</span>
                   </div>
                   {scanResult.version && (
                     <div>
                       <span className="text-muted-foreground">Version:</span>{" "}
                       <Badge variant="outline">{scanResult.version}</Badge>
                     </div>
                   )}
                   <div>
                     <span className="text-muted-foreground">Main file:</span>{" "}
                     <code className="text-xs bg-muted px-1 rounded">{scanResult.mainFile}</code>
                   </div>
                   <div>
                     <span className="text-muted-foreground">Files:</span>{" "}
                     {scanResult.fileCount} ({formatFileSize(scanResult.totalSize)})
                   </div>
                   {scanResult.author && (
                     <div className="col-span-2">
                       <span className="text-muted-foreground">Author:</span>{" "}
                       {scanResult.author}
                     </div>
                   )}
                 </div>
 
                 {!isAlreadyRegistered && (
                   <Button
                     onClick={handleAddPlugin}
                     disabled={isAdding}
                     className="w-full"
                   >
                     {isAdding ? (
                       <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                     ) : (
                       <Plus className="h-4 w-4 mr-2" />
                     )}
                     Register "{scanResult.pluginName}"
                   </Button>
                 )}
               </>
             ) : (
               <p className="text-sm text-muted-foreground">{scanResult.error}</p>
             )}
           </div>
         )}
       </CardContent>
     </Card>
   );
 }