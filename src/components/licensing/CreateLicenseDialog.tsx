// Create License dialog.

import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useCreateLicense } from "@/hooks/useLicensing";
import type { LicenseType, ProductType } from "@/types/licensing";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function CreateLicenseDialog({ open, onOpenChange }: Props) {
  const [email, setEmail] = useState("");
  const [licenseType, setLicenseType] = useState<LicenseType>("standard");
  const [product] = useState<ProductType>("riseup-uploader");
  const [maxActivations, setMaxActivations] = useState("1");
  const [notes, setNotes] = useState("");

  const createMutation = useCreateLicense();

  const handleSubmit = () => {
    const isEmailEmpty = email.trim() === "";
    if (isEmailEmpty) return;

    createMutation.mutate(
      {
        email: email.trim(),
        product,
        type: licenseType,
        maxActivations: parseInt(maxActivations, 10) || 1,
        notes: notes.trim() || undefined,
      },
      {
        onSuccess: () => {
          onOpenChange(false);
          resetForm();
        },
      }
    );
  };

  const resetForm = () => {
    setEmail("");
    setLicenseType("standard");
    setMaxActivations("1");
    setNotes("");
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Create License</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              placeholder="customer@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>License Type</Label>
              <Select value={licenseType} onValueChange={(v) => setLicenseType(v as LicenseType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="standard">Standard</SelectItem>
                  <SelectItem value="professional">Professional</SelectItem>
                  <SelectItem value="enterprise">Enterprise</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="maxActivations">Max Activations</Label>
              <Input
                id="maxActivations"
                type="number"
                min="1"
                value={maxActivations}
                onChange={(e) => setMaxActivations(e.target.value)}
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label>Product</Label>
            <Input value="Riseup Uploader" disabled className="text-muted-foreground" />
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">Notes (optional)</Label>
            <Textarea
              id="notes"
              placeholder="Internal notes about this license..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={createMutation.isPending || !email.trim()}>
            {createMutation.isPending ? "Creating…" : "Create License"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
