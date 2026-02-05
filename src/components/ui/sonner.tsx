import { useTheme } from "next-themes";
import { Toaster as Sonner, toast } from "sonner";

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme();

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      position="bottom-right"
      richColors
      closeButton
      toastOptions={{
        classNames: {
          // Solid dark gray background like VS Code - NOT glassy
          toast:
            "group toast group-[.toaster]:bg-[hsl(220,13%,18%)] group-[.toaster]:text-[hsl(210,40%,98%)] group-[.toaster]:border group-[.toaster]:border-[hsl(220,13%,26%)] group-[.toaster]:shadow-2xl group-[.toaster]:rounded-lg",
          description: "group-[.toast]:text-[hsl(215,20%,65%)]",
          actionButton: "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground group-[.toast]:rounded-md group-[.toast]:font-medium",
          cancelButton: "group-[.toast]:bg-[hsl(220,13%,26%)] group-[.toast]:text-[hsl(215,20%,65%)] group-[.toast]:rounded-md",
          // Red close button - larger on mobile with touch-friendly sizing
          closeButton: "group-[.toast]:!bg-[hsl(0,62%,45%)] group-[.toast]:!border-[hsl(0,62%,35%)] group-[.toast]:!text-white hover:group-[.toast]:!bg-[hsl(0,72%,50%)] group-[.toast]:!rounded-md group-[.toast]:!w-6 group-[.toast]:!h-6 sm:group-[.toast]:!w-5 sm:group-[.toast]:!h-5 group-[.toast]:!right-2 group-[.toast]:!top-2",
          // Status-specific styling - solid backgrounds, not transparent
          success: "group-[.toaster]:!bg-[hsl(142,40%,20%)] group-[.toaster]:!border-[hsl(142,50%,30%)] group-[.toaster]:!text-[hsl(142,70%,70%)]",
          error: "group-[.toaster]:!bg-[hsl(0,40%,20%)] group-[.toaster]:!border-[hsl(0,50%,35%)] group-[.toaster]:!text-[hsl(0,70%,70%)]",
          warning: "group-[.toaster]:!bg-[hsl(38,40%,20%)] group-[.toaster]:!border-[hsl(38,50%,35%)] group-[.toaster]:!text-[hsl(38,80%,65%)]",
          info: "group-[.toaster]:!bg-[hsl(210,40%,20%)] group-[.toaster]:!border-[hsl(210,50%,35%)] group-[.toaster]:!text-[hsl(210,70%,70%)]",
        },
      }}
      {...props}
    />
  );
};

export { Toaster, toast };
