// PLACEHOLDER PHOTOS FOR THE «WHY» SHEETS (work order CLIENT-START §3, canvas 41-2 «Слоты файлов»).
//
// The canvas names seven photo slots and says «для бандла подбирает Ден»: until he does, each slot is a warm tone —
// the scene cards of sheet a in the canvas's own tones, the photos of c and d a gradient dark enough at the top for the
// light status bar. Same file names as the canvas, so a real photo replaces a placeholder without a code change.
// Run from mobile/:  xcrun swift scripts/brand/intro_placeholders.swift
import CoreGraphics
import Foundation
import ImageIO
import UniformTypeIdentifiers

func hex(_ v: UInt32) -> CGColor {
  CGColor(srgbRed: CGFloat((v >> 16) & 0xFF) / 255, green: CGFloat((v >> 8) & 0xFF) / 255, blue: CGFloat(v & 0xFF) / 255, alpha: 1)
}

func write(_ name: String, width: Int, height: Int, top: UInt32, bottom: UInt32) {
  let ctx = CGContext(data: nil, width: width, height: height, bitsPerComponent: 8, bytesPerRow: 0,
                      space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
  let gradient = CGGradient(colorsSpace: CGColorSpace(name: CGColorSpace.sRGB)!, colors: [hex(top), hex(bottom)] as CFArray, locations: [0, 1])!
  // CoreGraphics' y goes up: the «top» colour at the image's top edge.
  ctx.drawLinearGradient(gradient, start: CGPoint(x: 0, y: height), end: CGPoint(x: 0, y: 0), options: [])
  let url = URL(fileURLWithPath: "assets/intro/\(name)")
  let dest = CGImageDestinationCreateWithURL(url as CFURL, UTType.jpeg.identifier as CFString, 1, nil)!
  CGImageDestinationAddImage(dest, ctx.makeImage()!, [kCGImageDestinationLossyCompressionQuality: 0.85] as CFDictionary)
  precondition(CGImageDestinationFinalize(dest))
  print(name)
}

// a · the five scene cards (160 × 208 at 3×) — the canvas's tones, a touch of depth top to bottom.
write("scene-airport.jpg", width: 480, height: 624, top: 0xC9BFB0, bottom: 0xBDB2A2)
write("scene-bank.jpg", width: 480, height: 624, top: 0xE2D8C8, bottom: 0xD6CBBA)
write("scene-interview.jpg", width: 480, height: 624, top: 0xCDBFAE, bottom: 0xC1B2A0)
write("scene-rent.jpg", width: 480, height: 624, top: 0xD9CFC0, bottom: 0xCDC2B2)
write("scene-doctor.jpg", width: 480, height: 624, top: 0xD2C4B2, bottom: 0xC6B7A4)
// c · d — the photo behind the top of the sheet (390 × 560 at 3×), dark at the top for the light status bar.
write("doctor-office.jpg", width: 1170, height: 1680, top: 0x4B4037, bottom: 0xC9BBA8)
write("cafe.jpg", width: 1170, height: 1680, top: 0x51443A, bottom: 0xCFC2B0)
// e · the three store covers (112 × 124 at 3×).
write("cover-city.jpg", width: 336, height: 372, top: 0xD8CFC1, bottom: 0xCBC0B0)
write("cover-health.jpg", width: 336, height: 372, top: 0xD3CABB, bottom: 0xC7BCAB)
write("cover-work.jpg", width: 336, height: 372, top: 0xDBD2C4, bottom: 0xCEC4B4)
