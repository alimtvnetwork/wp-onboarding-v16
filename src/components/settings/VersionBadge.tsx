import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Sparkles } from "lucide-react";
import { useWhatsNew } from "@/hooks/useWhatsNew";
import { WhatsNewModal } from "./WhatsNewModal";

interface VersionBadgeProps {
  className?: string;
  showUpdateIndicator?: boolean;
}

export function VersionBadge({ className, showUpdateIndicator = true }: VersionBadgeProps) {
  const { currentVersion, hasNewVersion, isLoading, openModal, isModalOpen, closeModal } = useWhatsNew();

  if (isLoading) {
    return (
      <Badge variant="outline" className={className}>
        <span className="animate-pulse">Loading...</span>
      </Badge>
    );
  }

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        className={`h-auto p-0 hover:bg-transparent ${className}`}
        onClick={openModal}
      >
        <Badge 
          variant={hasNewVersion ? "default" : "outline"} 
          className="cursor-pointer hover:opacity-80 transition-opacity"
        >
          {showUpdateIndicator && hasNewVersion && (
            <Sparkles className="mr-1 h-3 w-3" />
          )}
          v{currentVersion}
          {showUpdateIndicator && hasNewVersion && (
            <span className="ml-1 text-xs opacity-75">(new!)</span>
          )}
        </Badge>
      </Button>
      <WhatsNewModal open={isModalOpen} onOpenChange={(open) => !open && closeModal()} />
    </>
  );
}
