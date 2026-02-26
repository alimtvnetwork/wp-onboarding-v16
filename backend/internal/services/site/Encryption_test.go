// Package site - Unit tests for encryption
package site

import (
	"bytes"
	"testing"
)

func TestEncryptDecrypt(t *testing.T) {
	key := []byte("my-secret-encryption-key-32chars")
	plaintext := []byte("xxxx xxxx xxxx xxxx xxxx xxxx")

	// Encrypt
	ciphertext, err := encrypt(plaintext, key)
	if err != nil {
		t.Fatalf("encrypt failed: %v", err)
	}

	// Verify ciphertext is different from plaintext
	if bytes.Equal(ciphertext, plaintext) {
		t.Error("ciphertext should differ from plaintext")
	}

	// Decrypt
	decrypted, err := decrypt(ciphertext, key)
	if err != nil {
		t.Fatalf("decrypt failed: %v", err)
	}

	// Verify decrypted matches original
	if !bytes.Equal(decrypted, plaintext) {
		t.Errorf("decrypted text doesn't match: got %s, want %s", decrypted, plaintext)
	}
}

func TestEncryptDecryptDifferentCiphertext(t *testing.T) {
	key := []byte("my-secret-encryption-key-32chars")
	plaintext := []byte("same message")

	// Encrypt twice
	ciphertext1, _ := encrypt(plaintext, key)
	ciphertext2, _ := encrypt(plaintext, key)

	// Each encryption should produce different ciphertext (due to random nonce)
	if bytes.Equal(ciphertext1, ciphertext2) {
		t.Error("encrypting same plaintext should produce different ciphertexts")
	}

	// Both should decrypt to the same plaintext
	decrypted1, _ := decrypt(ciphertext1, key)
	decrypted2, _ := decrypt(ciphertext2, key)

	if !bytes.Equal(decrypted1, decrypted2) {
		t.Error("both ciphertexts should decrypt to the same plaintext")
	}
}

func TestDecryptWrongKey(t *testing.T) {
	key1 := []byte("correct-encryption-key")
	key2 := []byte("wrong-encryption-key")
	plaintext := []byte("secret data")

	ciphertext, _ := encrypt(plaintext, key1)

	// Try to decrypt with wrong key
	_, err := decrypt(ciphertext, key2)
	if err == nil {
		t.Error("decrypt with wrong key should fail")
	}
}

func TestDecryptEmptyCiphertext(t *testing.T) {
	key := []byte("my-key")

	_, err := decrypt([]byte{}, key)
	if err == nil {
		t.Error("decrypt empty ciphertext should fail")
	}
}

func TestDecryptTooShort(t *testing.T) {
	key := []byte("my-key")

	_, err := decrypt([]byte{1, 2, 3}, key)
	if err == nil {
		t.Error("decrypt too-short ciphertext should fail")
	}
}
